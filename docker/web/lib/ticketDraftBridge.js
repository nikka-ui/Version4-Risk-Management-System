/**
 * Ticket workflow facade for Phase 3 slice 6.
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
  getTicketByRef,
} = require('./tickets');
const { USE_LARAVEL_API } = require('../config/features');
const laravelApi = require('./laravelApi');

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

async function createTicket(username, displayName, body, options = {}) {
  const result = await expressCreateTicket(username, displayName, body, options);
  if (!USE_LARAVEL_API || result.error || !result.ticket) return result;
  try {
    await laravelApi.mirrorDraftCreate(result.ticket, username);
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
  isLaravelDraftBridgeEnabled: () => USE_LARAVEL_API,
  getTicketByRef,
};
