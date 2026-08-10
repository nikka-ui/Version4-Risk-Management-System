const { findUserRecord, publicUser } = require('./store');
const { roleDashboardPath } = require('../config/roles');

const ROLE_TICKET_PATH = {
  supervisor: '/supervisor/tickets',
  dept_head: '/dept/tickets',
  rm_officer: '/officer/tickets',
  executive: '/executive/tickets',
  president: '/president/tickets',
};

/**
 * If an authenticated user hits another role's ticket detail URL (common with
 * legacy notification links), send them to the same ticket in their own console.
 */
function redirectToOwnTicketConsole(req, res, expectedRole) {
  const user = req.session?.user;
  if (!user || user.role === expectedRole) return false;
  if (req.method !== 'GET') return false;

  const match = String(req.path || '').match(/\/tickets\/([^/]+)\/?$/);
  if (!match) return false;

  const ref = match[1];
  if (!ref || ref === 'new') return false;

  const base = ROLE_TICKET_PATH[user.role];
  if (base) {
    res.redirect(`${base}/${encodeURIComponent(ref)}`);
    return true;
  }

  res.redirect(roleDashboardPath(user.role));
  return true;
}

function requireAuth(req, res, next) {
  if (req.session?.user) {
    return next();
  }
  const nextUrl = encodeURIComponent(req.originalUrl || '/dashboard');
  return res.redirect(`/login?next=${nextUrl}`);
}

function requireAdmin(req, res, next) {
  if (!req.session?.user) {
    return res.redirect('/login');
  }
  if (req.session.user.role !== 'admin') {
    return res.status(403).send('Administrator access only.');
  }
  return next();
}

function requireSupervisor(req, res, next) {
  if (!req.session?.user) {
    return res.redirect('/login');
  }
  if (req.session.user.role !== 'supervisor') {
    if (redirectToOwnTicketConsole(req, res, 'supervisor')) return undefined;
    return res.status(403).send('Ticket Reporter access only.');
  }
  return next();
}

function requireDeptHead(req, res, next) {
  if (!req.session?.user) {
    return res.redirect('/login');
  }
  if (req.session.user.role !== 'dept_head') {
    if (redirectToOwnTicketConsole(req, res, 'dept_head')) return undefined;
    return res.status(403).send('Department Head / Vice President access only.');
  }
  return next();
}

function requireRmOfficer(req, res, next) {
  if (!req.session?.user) {
    return res.redirect('/login');
  }
  if (req.session.user.role !== 'rm_officer') {
    if (redirectToOwnTicketConsole(req, res, 'rm_officer')) return undefined;
    return res.status(403).send('Risk Management Officer (RMO) access only.');
  }
  return next();
}

function requireExecutive(req, res, next) {
  if (!req.session?.user) {
    return res.redirect('/login');
  }
  if (req.session.user.role !== 'executive') {
    if (redirectToOwnTicketConsole(req, res, 'executive')) return undefined;
    return res.status(403).send('Executive Committee access only.');
  }
  return next();
}

function requirePresident(req, res, next) {
  if (!req.session?.user) {
    return res.redirect('/login');
  }
  if (req.session.user.role !== 'president') {
    if (redirectToOwnTicketConsole(req, res, 'president')) return undefined;
    return res.status(403).send('President access only.');
  }
  return next();
}

function requireRole(...roles) {
  return (req, res, next) => {
    if (!req.session?.user) {
      return res.redirect('/login');
    }
    if (roles.includes(req.session.user.role)) {
      return next();
    }
    return res.status(403).send('Access denied for your role.');
  };
}

function authenticate(username, password) {
  const record = findUserRecord(username);
  if (!record) {
    return { error: 'invalid_username' };
  }
  if (record.password !== password) {
    return { error: 'invalid_password' };
  }
  return { user: sessionUser(record) };
}

function sessionUserFromLaravel(identity) {
  return {
    username: identity.username,
    role: identity.role,
    roleLabel: identity.roleLabel,
    displayName: identity.displayName,
    email: identity.email || '',
    department: identity.department || '',
    position: identity.position || '',
    employeeId: identity.employeeId || '',
    canManageUsers: Boolean(identity.canManageUsers),
  };
}

/**
 * Phase 5 slice 2: async login. When USE_LARAVEL_AUTH=true, verify via Laravel;
 * otherwise use Express store.json plaintext auth.
 */
async function authenticateAsync(username, password) {
  const {
    USE_LARAVEL_AUTH,
    USE_LARAVEL_AUTH_FALLBACK,
  } = require('../config/features');

  if (!USE_LARAVEL_AUTH) {
    return authenticate(username, password);
  }

  try {
    const laravelApi = require('./laravelApi');
    const data = await laravelApi.verifyCredentials(username, password);
    if (!data?.user?.username) {
      return { error: 'invalid_password' };
    }
    return { user: sessionUserFromLaravel(data.user) };
  } catch (err) {
    const status = err?.status;
    if (status === 422) {
      const msg = String(err?.message || '').toLowerCase();
      if (msg.includes('inactive')) return { error: 'inactive_account' };
      // Laravel collapses unknown/bad password into one message.
      const record = findUserRecord(username);
      if (!record) return { error: 'invalid_username' };
      return { error: 'invalid_password' };
    }
    if (USE_LARAVEL_AUTH_FALLBACK && (!status || status >= 500)) {
      console.warn('[laravel-auth] verify unavailable, falling back to store:', err?.message || err);
      return authenticate(username, password);
    }
    console.warn('[laravel-auth] verify failed:', err?.message || err);
    return { error: 'auth_unavailable' };
  }
}

/**
 * Best-effort dual-write of user credentials/profile to Laravel when auth flag is on.
 */
function fireUserSync(adminUsername, payload) {
  const { USE_LARAVEL_AUTH } = require('../config/features');
  if (!USE_LARAVEL_AUTH) return;
  Promise.resolve()
    .then(() => require('./laravelApi').syncUser(adminUsername, payload))
    .catch((err) => console.warn('[laravel-auth] user sync failed:', err?.message || err));
}

function sessionUser(record) {
  const pub = publicUser(record);
  return {
    username: pub.username,
    role: pub.role,
    roleLabel: pub.roleLabel,
    displayName: pub.displayName,
    email: pub.email,
    department: pub.department,
    position: pub.position,
    employeeId: pub.employeeId,
    canManageUsers: pub.canManageUsers,
  };
}

function refreshSessionUser(req, username) {
  const { findUserRecord } = require('./store');
  const record = findUserRecord(username);
  if (record && req.session) {
    req.session.user = sessionUser(record);
  }
}

module.exports = {
  requireAuth,
  requireAdmin,
  requireSupervisor,
  requireDeptHead,
  requireRmOfficer,
  requireExecutive,
  requirePresident,
  requireRole,
  authenticate,
  authenticateAsync,
  sessionUser,
  sessionUserFromLaravel,
  refreshSessionUser,
  fireUserSync,
};
