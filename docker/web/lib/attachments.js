/**
 * Evidence storage: file bytes in MinIO/S3 (separate container), metadata in PostgreSQL.
 *
 * Phase 3 slice 11: when USE_LARAVEL_API=true, browser upload/download is routed
 * through Laravel (shared MinIO + risk_attachments). Default remains Express-local.
 */
const attachmentRepo = require('./attachmentRepository');
const objectStorage = require('./objectStorage');
const { USE_LARAVEL_API } = require('../config/features');

const MAX_FILE_BYTES = 20 * 1024 * 1024;
const MAX_FILES_PER_TICKET = 10;
const ALLOWED_EXT = new Set(['pdf', 'png', 'jpg', 'jpeg']);
const ALLOWED_MIME = new Set([
  'application/pdf',
  'image/png',
  'image/jpeg',
]);

function sanitizeFilename(name) {
  const path = require('path');
  const base = path.basename(String(name || 'file'));
  return base.replace(/[^a-zA-Z0-9._-]/g, '_').slice(0, 120) || 'file';
}

function safeTicketRef(ticketRef) {
  return String(ticketRef || '').replace(/[^a-zA-Z0-9._-]/g, '_');
}

function extFromName(name) {
  const parts = String(name || '').toLowerCase().split('.');
  return parts.length > 1 ? parts[parts.length - 1] : '';
}

function validateUpload(file) {
  if (!file || !file.buffer) {
    return { ok: false, error: 'Invalid upload.' };
  }
  if (file.size > MAX_FILE_BYTES) {
    return { ok: false, error: `File exceeds 20MB: ${file.originalname}` };
  }
  const ext = extFromName(file.originalname);
  if (!ALLOWED_EXT.has(ext)) {
    return { ok: false, error: `Unsupported file type: ${ext || 'unknown'}` };
  }
  if (file.mimetype && !ALLOWED_MIME.has(file.mimetype)) {
    return { ok: false, error: `Unsupported MIME type: ${file.mimetype}` };
  }
  return { ok: true };
}

async function saveUploadedFilesViaLaravel(ticketRef, files, { uploadedBy } = {}) {
  const list = Array.isArray(files) ? files : [];
  const validated = [];
  for (const file of list.slice(0, MAX_FILES_PER_TICKET)) {
    const check = validateUpload(file);
    if (!check.ok) return { error: check.error };
    validated.push(file);
  }
  if (!validated.length) return { attachments: [] };

  const laravelApi = require('./laravelApi');
  const username = uploadedBy || process.env.LARAVEL_SYSTEM_USERNAME || 'admin';
  try {
    const data = await laravelApi.uploadAttachments(ticketRef, username, validated);
    const attachments = (data?.attachments || []).map((a) => ({
      id: a.id,
      ticketRef: a.ticketRef || ticketRef,
      name: a.originalName || a.name,
      originalName: a.originalName || a.name,
      mimeType: a.mimeType,
      size: Number(a.size || 0),
      storageKey: a.storageKey,
      uploadedBy: a.uploadedBy || uploadedBy || null,
      uploadedAt: a.uploadedAt || new Date().toISOString(),
      legacy: Boolean(a.legacy),
    }));
    return { attachments };
  } catch (err) {
    console.warn('[laravel-bridge] attachment upload failed:', err?.message || err);
    return { error: err?.message || 'Laravel attachment upload failed.' };
  }
}

async function saveUploadedFiles(ticketRef, files, { uploadedBy } = {}) {
  if (USE_LARAVEL_API) {
    return saveUploadedFilesViaLaravel(ticketRef, files, { uploadedBy });
  }

  const saved = [];
  const list = Array.isArray(files) ? files : [];
  const ref = safeTicketRef(ticketRef);

  for (const file of list.slice(0, MAX_FILES_PER_TICKET)) {
    const check = validateUpload(file);
    if (!check.ok) {
      return { error: check.error };
    }

    const id = `att-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
    const safeName = sanitizeFilename(file.originalname);
    const storageKey = `${ref}/${id}-${safeName}`;
    const mimeType = file.mimetype || 'application/octet-stream';

    await objectStorage.putObject(storageKey, file.buffer, mimeType);
    const record = await attachmentRepo.insertAttachment({
      id,
      ticketRef: ticketRef,
      originalName: file.originalname,
      mimeType,
      size: file.size,
      storageKey,
      uploadedBy: uploadedBy || null,
      legacy: false,
    });
    saved.push(record);
  }

  return { attachments: saved };
}

async function saveLegacyEvidenceReferences(ticketRef, items, { uploadedBy } = {}) {
  const saved = [];
  for (const item of items || []) {
    const record = await attachmentRepo.insertAttachment({
      id: item.id,
      ticketRef,
      originalName: item.name || item.originalName || 'reference',
      mimeType: item.mimeType || 'application/octet-stream',
      size: item.size || 0,
      storageKey: item.storageKey || `legacy/${safeTicketRef(ticketRef)}/${item.id}`,
      uploadedBy: uploadedBy || null,
      legacy: true,
      uploadedAt: item.uploadedAt || null,
    });
    saved.push(record);
  }
  return saved;
}

async function deleteStoredFile(storageKey) {
  if (!storageKey || storageKey.startsWith('legacy/')) return;
  await objectStorage.deleteObject(storageKey);
}

async function deleteTicketUploads(ticketRef) {
  const keys = await attachmentRepo.deleteByTicketRef(ticketRef);
  const objectKeys = keys.filter((k) => k && !k.startsWith('legacy/'));
  if (objectKeys.length) {
    await objectStorage.deleteObjects(objectKeys);
  }
}

async function removeAttachmentsFromTicket(ticket, attachmentIds) {
  const ids = new Set(Array.isArray(attachmentIds) ? attachmentIds : []);
  if (!ids.size) return [];

  const removedRows = await attachmentRepo.deleteByIds([...ids]);
  const keys = removedRows.map((r) => r.storage_key).filter((k) => k && !k.startsWith('legacy/'));
  if (keys.length) {
    await objectStorage.deleteObjects(keys);
  }

  if (ticket?.evidence) {
    ticket.evidence = ticket.evidence.filter((a) => !ids.has(a.id));
  }
  return removedRows;
}

async function hydrateTicketEvidence(ticket) {
  if (!ticket?.reference) {
    ticket.evidence = [];
    return ticket;
  }
  ticket.evidence = await attachmentRepo.listByTicketRef(ticket.reference);
  ticket.evidenceCount = ticket.evidence.length;
  return ticket;
}

function resolveAttachmentStorageKey(found) {
  return found?.attachment?.storageKey || null;
}

async function streamAttachmentViaLaravel(res, found, { username } = {}) {
  const att = found?.attachment;
  if (!att?.id) {
    res.status(404).send('Attachment not found.');
    return false;
  }
  if (att.legacy && String(att.storageKey || '').startsWith('legacy/')) {
    res.status(404).send('This evidence reference has no stored file.');
    return false;
  }

  try {
    const laravelApi = require('./laravelApi');
    const actor = username
      || att.uploadedBy
      || found?.ticket?.submittedBy
      || process.env.LARAVEL_SYSTEM_USERNAME
      || 'admin';
    const upstream = await laravelApi.downloadAttachment(actor, att.id);
    const contentType = upstream.headers.get('content-type') || att.mimeType || 'application/octet-stream';
    const disposition = upstream.headers.get('content-disposition')
      || `inline; filename="${encodeURIComponent(att.originalName || att.name || 'file')}"`;
    res.setHeader('Content-Type', contentType);
    res.setHeader('Content-Disposition', disposition);
    const { Readable } = require('stream');
    Readable.fromWeb(upstream.body).pipe(res);
    return true;
  } catch (err) {
    console.warn('[laravel-bridge] attachment download failed, falling back to MinIO:', err?.message || err);
    return false;
  }
}

async function streamAttachmentToResponse(res, found, { username } = {}) {
  const storageKey = resolveAttachmentStorageKey(found);
  const att = found?.attachment;
  if (!storageKey || !att) {
    res.status(404).send('Attachment not found.');
    return false;
  }
  if (att.legacy && storageKey.startsWith('legacy/')) {
    res.status(404).send('This evidence reference has no stored file.');
    return false;
  }

  if (USE_LARAVEL_API) {
    const ok = await streamAttachmentViaLaravel(res, found, { username });
    if (ok) return true;
  }

  try {
    const stream = await objectStorage.getObjectStream(storageKey);
    res.setHeader('Content-Type', att.mimeType || 'application/octet-stream');
    res.setHeader(
      'Content-Disposition',
      `inline; filename="${encodeURIComponent(att.originalName || att.name)}"`,
    );
    stream.pipe(res);
    return true;
  } catch {
    res.status(404).send('File not found in object storage.');
    return false;
  }
}

async function initializeAttachmentStorage() {
  const { ensureSchema } = require('./db');
  await ensureSchema();
  await objectStorage.ensureBucket();
}

module.exports = {
  MAX_FILES_PER_TICKET,
  saveUploadedFiles,
  saveLegacyEvidenceReferences,
  deleteStoredFile,
  deleteTicketUploads,
  removeAttachmentsFromTicket,
  hydrateTicketEvidence,
  resolveAttachmentStorageKey,
  streamAttachmentToResponse,
  initializeAttachmentStorage,
};
