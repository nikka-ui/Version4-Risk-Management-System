/**
 * Ticket workflow facade for Phase 3 slice 8.
 *
 * Default (USE_LARAVEL_API=false): Express tickets.js only — store.json SoT.
 * When USE_LARAVEL_API=true: Express write first, then best-effort Laravel mirror.
 */
const {
  createTicket: expressCreateTicket,
  updateTicketDraft: expressUpdateTicketDraft,
  deleteDraftTicket: expressDeleteDraftTicket,
  submitTicket: expressSubmitTicket,
  acceptOwnership: expressAcceptOwnership,
  rejectOwnership: expressRejectOwnership,
  saveActionPlan: expressSaveActionPlan,
  returnTicketForRevision: expressReturnTicketForRevision,
  reassignTicket: expressReassignTicket,
  closeTicketAsDeptHead: expressCloseTicketAsDeptHead,
  recordPresidentDecision: expressRecordPresidentDecision,
  assignPersonnel: expressAssignPersonnel,
  uploadDeptDocuments: expressUploadDeptDocuments,
  addDeptHeadThreadComment: expressAddDeptHeadThreadComment,
  addReporterThreadComment: expressAddReporterThreadComment,
  addRmuThreadComment: expressAddRmuThreadComment,
  reopenTicketAsOfficer: expressReopenTicketAsOfficer,
  addEvidence: expressAddEvidence,
  getTicketByRef,
} = require('./tickets');
const { USE_LARAVEL_API } = require('../config/features');
const laravelApi = require('./laravelApi');
const attachmentRepo = require('./attachmentRepository');

function logMirrorError(action, detail, err) {
  console.warn(
    `[laravel-bridge] ${action} mirror failed (${detail}):`,
    err?.message || err,
  );
}

function fireMirror(action, reference, fn) {
  Promise.resolve()
    .then(fn)
    .catch((err) => logMirrorError(action, reference, err));
}

function toMirrorAttachment(a) {
  return {
    id: a.id,
    originalName: a.originalName || a.name,
    mimeType: a.mimeType || 'application/octet-stream',
    size: a.size || 0,
    storageKey: a.storageKey,
    uploadedBy: a.uploadedBy || null,
    uploadedAt: a.uploadedAt || null,
    legacy: Boolean(a.legacy),
  };
}

function fireAttachmentMirror(reference, username, evidenceItems) {
  fireMirror('attachments', reference, async () => {
    const items = Array.isArray(evidenceItems) ? evidenceItems.filter((a) => a?.id) : [];
    if (items.length) {
      await laravelApi.mirrorAttachmentRegister(reference, username, {
        attachments: items.map(toMirrorAttachment),
      });
      return;
    }
    await laravelApi.mirrorAttachmentSync(reference, username);
  });
}

async function createTicket(username, displayName, body, options = {}) {
  const result = await expressCreateTicket(username, displayName, body, options);
  if (!USE_LARAVEL_API || result.error || !result.ticket) return result;
  try {
    await laravelApi.mirrorDraftCreate(result.ticket, username);
    if (options.uploadedFiles?.length || result.ticket.evidenceCount > 0) {
      const listed = await attachmentRepo.listByTicketRef(result.ticket.reference);
      fireAttachmentMirror(result.ticket.reference, username, listed);
    }
  } catch (err) {
    logMirrorError('create', result.ticket.reference, err);
  }
  return result;
}

async function updateTicketDraft(reference, username, body, options = {}) {
  const result = await expressUpdateTicketDraft(reference, username, body, options);
  if (!USE_LARAVEL_API || result.error || !result.ticket) return result;
  if (result.ticket.status !== 'draft') return result;
  try {
    await laravelApi.mirrorDraftUpdate(result.ticket, username);
  } catch (err) {
    try {
      await laravelApi.mirrorDraftCreate(result.ticket, username);
    } catch (err2) {
      logMirrorError('update', reference, err2);
    }
  }
  if (options.uploadedFiles?.length) {
    const listed = await attachmentRepo.listByTicketRef(reference).catch(() => []);
    fireAttachmentMirror(reference, username, listed);
  }
  return result;
}

async function deleteDraftTicket(reference, username) {
  const result = await expressDeleteDraftTicket(reference, username);
  if (!USE_LARAVEL_API || result.error) return result;
  try {
    await laravelApi.mirrorDraftDelete(reference, username);
  } catch (err) {
    logMirrorError('delete', reference, err);
  }
  return result;
}

function submitTicket(reference, username, displayName) {
  const result = expressSubmitTicket(reference, username, displayName);
  if (!USE_LARAVEL_API || result.error || !result.ticket) return result;
  fireMirror('submit', reference, async () => {
    try {
      await laravelApi.mirrorDraftCreate(result.ticket, username);
    } catch (_) {
      /* may already exist */
    }
    await laravelApi.mirrorSubmit(reference, username);
  });
  return result;
}

function acceptOwnership(reference, user, body = {}) {
  const result = expressAcceptOwnership(reference, user, body);
  if (!USE_LARAVEL_API || result.error || !result.ticket) return result;
  fireMirror('accept', reference, () =>
    laravelApi.mirrorAccept(reference, user.username, {
      comment: body.comment || '',
    }),
  );
  return result;
}

function rejectOwnership(reference, user, body = {}) {
  const result = expressRejectOwnership(reference, user, body);
  if (!USE_LARAVEL_API || result.error || !result.ticket) return result;
  fireMirror('reject', reference, () =>
    laravelApi.mirrorReject(reference, user.username, {
      reason: body.reason || body.comment || '',
      comment: body.comment || '',
    }),
  );
  return result;
}

function saveActionPlan(reference, user, body = {}) {
  const result = expressSaveActionPlan(reference, user, body);
  if (!USE_LARAVEL_API || result.error || !result.ticket) return result;
  fireMirror('action-plan', reference, () =>
    laravelApi.mirrorActionPlan(reference, user.username, {
      summary: body.summary || '',
      steps: body.steps || '',
      targetDate: body.targetDate || '',
      submitForReview: body.submitForReview,
    }),
  );
  return result;
}

function returnTicketForRevision(reference, user, body = {}) {
  const result = expressReturnTicketForRevision(reference, user, body);
  if (!USE_LARAVEL_API || result.error || !result.ticket) return result;
  fireMirror('return', reference, () =>
    laravelApi.mirrorReturn(reference, user.username, {
      reason: body.reason || body.comment || '',
    }),
  );
  return result;
}

function reassignTicket(reference, user, body = {}) {
  const result = expressReassignTicket(reference, user, body);
  if (!USE_LARAVEL_API || result.error || !result.ticket) return result;
  fireMirror('reassign', reference, () =>
    laravelApi.mirrorReassign(reference, user.username, {
      reason: body.reason || '',
      comment: body.comment || '',
      targetDepartment: body.targetDepartment || '',
    }),
  );
  return result;
}

function closeTicketAsDeptHead(reference, user, body = {}) {
  const result = expressCloseTicketAsDeptHead(reference, user, body);
  if (!USE_LARAVEL_API || result.error || !result.ticket) return result;
  fireMirror('close', reference, () =>
    laravelApi.mirrorClose(reference, user.username, {
      closingNotes: body.closingNotes || body.notes || body.summary || '',
    }),
  );
  return result;
}

function recordPresidentDecision(reference, user, body = {}) {
  const result = expressRecordPresidentDecision(reference, user, body);
  if (!USE_LARAVEL_API || result.error || !result.ticket) return result;
  fireMirror('president-decision', reference, () =>
    laravelApi.mirrorPresidentDecision(reference, user.username, {
      decision: body.decision || '',
      note: body.note || body.comment || '',
    }),
  );
  return result;
}

function assignPersonnel(reference, user, body = {}) {
  const result = expressAssignPersonnel(reference, user, body);
  if (!USE_LARAVEL_API || result.error || !result.ticket) return result;
  fireMirror('personnel', reference, () =>
    laravelApi.mirrorPersonnel(reference, user.username, {
      personName: body.personName || '',
      personRole: body.personRole || '',
    }),
  );
  return result;
}

async function uploadDeptDocuments(reference, user, options = {}) {
  const result = await expressUploadDeptDocuments(reference, user, options);
  if (!USE_LARAVEL_API || result.error || !result.ticket) return result;
  const files = options.uploadedFiles || [];
  fireMirror('documents', reference, async () => {
    await laravelApi.mirrorDocuments(reference, user.username, {
      fileCount: result.uploaded || files.length || 0,
      fileNames: files.map((f) => f.originalname || f.name || 'file'),
    });
    const listed = await attachmentRepo.listByTicketRef(reference);
    if (listed.length) {
      await laravelApi.mirrorAttachmentRegister(reference, user.username, {
        attachments: listed.map(toMirrorAttachment),
      });
    } else {
      await laravelApi.mirrorAttachmentSync(reference, user.username);
    }
  });
  return result;
}

async function addEvidence(reference, username, body = {}, options = {}) {
  const result = await expressAddEvidence(reference, username, body, options);
  if (!USE_LARAVEL_API || result.error || !result.ticket) return result;
  const listed = await attachmentRepo.listByTicketRef(reference).catch(() => []);
  fireAttachmentMirror(reference, username, listed);
  return result;
}

function addDeptHeadThreadComment(reference, user, body = {}, options = {}) {
  const result = expressAddDeptHeadThreadComment(reference, user, body, options);
  if (!USE_LARAVEL_API || result.error || !result.ticket) return result;
  fireMirror('comment', reference, () =>
    laravelApi.mirrorComment(reference, user.username, {
      comment: body.comment || body.body || '',
      parentId: body.parentId || '',
    }),
  );
  return result;
}

function addReporterThreadComment(reference, user, body = {}, options = {}) {
  const result = expressAddReporterThreadComment(reference, user, body, options);
  if (!USE_LARAVEL_API || result.error || !result.ticket) return result;
  fireMirror('comment', reference, () =>
    laravelApi.mirrorComment(reference, user.username, {
      comment: body.comment || body.body || '',
      parentId: body.parentId || '',
    }),
  );
  return result;
}

function addRmuThreadComment(reference, user, body = {}) {
  const result = expressAddRmuThreadComment(reference, user, body);
  if (!USE_LARAVEL_API || result.error || !result.ticket) return result;
  fireMirror('comment', reference, () =>
    laravelApi.mirrorComment(reference, user.username, {
      comment: body.comment || body.body || '',
      parentId: body.parentId || '',
    }),
  );
  return result;
}

function reopenTicketAsOfficer(reference, user, body = {}) {
  const result = expressReopenTicketAsOfficer(reference, user, body);
  if (!USE_LARAVEL_API || result.error || !result.ticket) return result;
  fireMirror('reopen', reference, () =>
    laravelApi.mirrorReopen(reference, user.username, {
      reason: body.reason || '',
      department: body.department || body.targetDepartment || '',
    }),
  );
  return result;
}

module.exports = {
  createTicket,
  updateTicketDraft,
  deleteDraftTicket,
  submitTicket,
  acceptOwnership,
  rejectOwnership,
  saveActionPlan,
  returnTicketForRevision,
  reassignTicket,
  closeTicketAsDeptHead,
  recordPresidentDecision,
  assignPersonnel,
  uploadDeptDocuments,
  addEvidence,
  addDeptHeadThreadComment,
  addReporterThreadComment,
  addRmuThreadComment,
  reopenTicketAsOfficer,
  isLaravelDraftBridgeEnabled: () => USE_LARAVEL_API,
  getTicketByRef,
};
