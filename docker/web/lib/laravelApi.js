/**
 * Thin HTTP client for Express → Laravel (rms-api) calls.
 * Used only when USE_LARAVEL_API is enabled (default OFF).
 */
const { findUserRecord } = require('./store');

const BASE_URL = (process.env.LARAVEL_API_BASE_URL || 'http://api:8080').replace(/\/$/, '');

async function request(method, path, { token, body } = {}) {
  const headers = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  };
  if (token) headers.Authorization = `Bearer ${token}`;

  const res = await fetch(`${BASE_URL}${path}`, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  });

  let data = null;
  const text = await res.text();
  if (text) {
    try {
      data = JSON.parse(text);
    } catch {
      data = { message: text };
    }
  }

  if (!res.ok) {
    const message = data?.message || data?.errors
      ? JSON.stringify(data.errors || data.message)
      : `Laravel API ${res.status}`;
    const err = new Error(message);
    err.status = res.status;
    err.data = data;
    throw err;
  }

  return data;
}

async function getTokenForUsername(username) {
  const user = findUserRecord(username, { includeInactive: true });
  if (!user?.password) {
    throw new Error(`Cannot mint Laravel token: password missing for ${username}`);
  }

  const data = await request('POST', '/v1/auth/token', {
    body: {
      username: user.username,
      password: user.password,
      device_name: 'express-bridge',
    },
  });

  return data.token;
}

/**
 * Mirror an Express draft into Laravel Postgres (best-effort sync).
 * Passes the Express reference so IDs stay aligned.
 */
async function mirrorDraftCreate(ticket, username) {
  const token = await getTokenForUsername(username);
  const w = ticket.fiveW1H || {};
  const body = {
    reference: ticket.reference,
    title: ticket.title,
    description: ticket.description,
    location: ticket.location,
    mitigationApproach: ticket.mitigationApproach,
    reporterDepartment: ticket.reporterDepartment,
    what: w.what,
    why: w.why,
    where: w.where,
    when: w.when,
    who: w.who,
    how: w.how,
    evidenceCount: ticket.evidenceCount || (ticket.evidence || []).length || 1,
  };

  try {
    return await request('POST', '/v1/tickets', { token, body });
  } catch (err) {
    // Idempotent dual-write: if Laravel already has this reference, patch instead.
    if (err.status === 422) {
      return request('PATCH', `/v1/tickets/${encodeURIComponent(ticket.reference)}`, {
        token,
        body,
      });
    }
    throw err;
  }
}

async function mirrorDraftUpdate(ticket, username) {
  const token = await getTokenForUsername(username);
  const w = ticket.fiveW1H || {};
  return request('PATCH', `/v1/tickets/${encodeURIComponent(ticket.reference)}`, {
    token,
    body: {
      title: ticket.title,
      description: ticket.description,
      location: ticket.location,
      mitigationApproach: ticket.mitigationApproach,
      what: w.what,
      why: w.why,
      where: w.where,
      when: w.when,
      who: w.who,
      how: w.how,
      evidenceCount: ticket.evidenceCount || (ticket.evidence || []).length || 1,
    },
  });
}

async function mirrorDraftDelete(reference, username) {
  const token = await getTokenForUsername(username);
  return request('DELETE', `/v1/tickets/${encodeURIComponent(reference)}`, { token });
}

async function mirrorSubmit(reference, username) {
  const token = await getTokenForUsername(username);
  return request('POST', `/v1/tickets/${encodeURIComponent(reference)}/submit`, { token });
}

async function mirrorAccept(reference, username, body = {}) {
  const token = await getTokenForUsername(username);
  return request('POST', `/v1/tickets/${encodeURIComponent(reference)}/accept`, { token, body });
}

async function mirrorReject(reference, username, body = {}) {
  const token = await getTokenForUsername(username);
  return request('POST', `/v1/tickets/${encodeURIComponent(reference)}/reject`, { token, body });
}

async function mirrorActionPlan(reference, username, body = {}) {
  const token = await getTokenForUsername(username);
  return request('PUT', `/v1/tickets/${encodeURIComponent(reference)}/action-plan`, { token, body });
}

async function mirrorReturn(reference, username, body = {}) {
  const token = await getTokenForUsername(username);
  return request('POST', `/v1/tickets/${encodeURIComponent(reference)}/return`, { token, body });
}

async function mirrorReassign(reference, username, body = {}) {
  const token = await getTokenForUsername(username);
  return request('POST', `/v1/tickets/${encodeURIComponent(reference)}/reassign`, { token, body });
}

async function mirrorClose(reference, username, body = {}) {
  const token = await getTokenForUsername(username);
  return request('POST', `/v1/tickets/${encodeURIComponent(reference)}/close`, { token, body });
}

async function mirrorPresidentDecision(reference, username, body = {}) {
  const token = await getTokenForUsername(username);
  return request('POST', `/v1/tickets/${encodeURIComponent(reference)}/president-decision`, { token, body });
}

module.exports = {
  BASE_URL,
  mirrorDraftCreate,
  mirrorDraftUpdate,
  mirrorDraftDelete,
  mirrorSubmit,
  mirrorAccept,
  mirrorReject,
  mirrorActionPlan,
  mirrorReturn,
  mirrorReassign,
  mirrorClose,
  mirrorPresidentDecision,
};
