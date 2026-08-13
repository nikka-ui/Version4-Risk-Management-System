/**
 * Feature flags for gradual Laravel migration.
 *
 * Phase 8 slice 3: USE_LARAVEL_DEPT_TICKET_MUTATIONS also covers POST /dept/tickets/:ref/documents.
 * Phase 8 slice 2: USE_LARAVEL_REPORTER_UPLOAD_MUTATIONS also covers POST /supervisor/tickets/:ref/{evidence,accomplishment}.
 * Phase 8 slice 1: USE_LARAVEL_REPORTER_UPLOAD_MUTATIONS — Express POST /supervisor/tickets/new/preview + /:ref/edit redirect to Blade.
 * Phase 7 slice 13: USE_LARAVEL_DEPT_TICKET_MUTATIONS also covers POST /dept/tickets/:ref/comment.
 * Phase 7 slice 12: USE_LARAVEL_PRESIDENT_TICKET_MUTATIONS also covers POST /president/tickets/:ref/comment.
 * Phase 7 slice 11: USE_LARAVEL_OFFICER_TICKET_MUTATIONS also covers POST /officer/tickets/:ref/thread-comment.
 * Phase 7 slice 10: USE_LARAVEL_EXECUTIVE_TICKET_MUTATIONS — Express POST /executive/tickets/:ref/comment redirect to Blade.
 * Phase 7 slice 9: USE_LARAVEL_PRESIDENT_TICKET_MUTATIONS — Express POST /president/tickets/:ref/decision redirect to Blade.
 * Phase 7 slice 8: USE_LARAVEL_OFFICER_TICKET_MUTATIONS — Express POST /officer/tickets/:ref/reopen redirect to Blade.
 * Phase 7 slice 7: USE_LARAVEL_REPORTER_TICKET_MUTATIONS — Express POST /supervisor/tickets preview save/submit + draft delete redirect to Blade.
 * Phase 7 slice 6: USE_LARAVEL_DEPT_TICKET_MUTATIONS — Express POST /dept/tickets/:ref/{accept,reject,return,reassign,action-plan,close} redirect to Blade.
 * Phase 7 slice 5: USE_LARAVEL_ADMIN_TICKET_MUTATIONS — Express POST /admin/tickets/:ref/delete redirect to Blade.
 * Phase 7 slice 4: USE_LARAVEL_ADMIN_SETTINGS_MUTATIONS — Express POST /admin/settings* redirect to Blade mutations.
 * Phase 7 slice 3: USE_LARAVEL_ADMIN_USER_MUTATIONS — Express POST /admin/users* redirect to Blade mutations.
 * Phase 7 slice 2: USE_LARAVEL_ADMIN_POS_MUTATIONS — Express POST /admin/positions* redirect to Blade mutations.
 * Phase 7 slice 1: USE_LARAVEL_ADMIN_DEPT_MUTATIONS — Express POST /admin/departments* redirect to Blade mutations.
 * Phase 6 slice 6: USE_LARAVEL_DASHBOARD_UI — Express GET /dashboard redirect to Blade (employee stub / console hop).
 * Phase 6 slice 5: USE_LARAVEL_OFFICER_ALIASES_UI — Express GET /officer/{ai-review,review,final-validation} redirect to Blade aliases.
 * Phase 6 slice 4: USE_LARAVEL_EXECUTIVE_TICKET_DETAIL_UI — Express GET /executive/tickets/:ref redirect to Blade.
 * Phase 6 slice 3: USE_LARAVEL_EXECUTIVE_PAGES_UI — Express GET /executive/{heatmap,reports,…} redirect to Blade.
 * Phase 6 slice 2: USE_LARAVEL_EDGE_UI — unprefixed /login + role consoles (GET → Laravel).
 * Phase 6 slice 1: USE_LARAVEL_EDGE_ROOT — edge nginx exact `/` → Laravel home redirect.
 * Phase 5 slice 31: USE_LARAVEL_PRESIDENT_TICKET_DETAIL_UI — Express GET /president/tickets/:ref redirect to Blade.
 * Phase 5 slice 30: USE_LARAVEL_PRESIDENT_QUEUES_UI — Express GET /president/{pending,high,critical,trends} redirect to Blade.
 * Phase 5 slice 29: USE_LARAVEL_PRESIDENT_DASHBOARD_UI — Express GET /president redirects to Blade.
 * Phase 5 slice 26: USE_LARAVEL_OFFICER_QUEUES_UI — Express GET /officer/{tickets,overdue,monitoring,action-plans} redirect to Blade.
 * Phase 5 slice 25: USE_LARAVEL_OFFICER_DASHBOARD_UI — Express GET /officer redirects to Blade.
 * Phase 5 slice 24: USE_LARAVEL_DEPT_TICKET_DETAIL_UI — Express GET /dept/tickets/:ref redirects to Blade.
 * Phase 5 slice 23: USE_LARAVEL_DEPT_QUEUES_UI — Express GET /dept/* queues redirect to Blade.
 * Phase 5 slice 22: USE_LARAVEL_DEPT_DASHBOARD_UI — Express GET /dept redirects to Blade.
 * Phase 5 slice 21: USE_LARAVEL_ADMIN_SETTINGS_UI — Express GET /admin/settings redirects to Blade.
 * Phase 5 slice 19: USE_LARAVEL_ADMIN_TICKET_DETAIL_UI — Express GET /admin/tickets/:ref redirects to Blade.
 * Phase 5 slice 18: USE_LARAVEL_ADMIN_TICKETS_UI — Express GET /admin/tickets redirects to Blade.
 * Phase 5 slice 17: USE_LARAVEL_ADMIN_POSITIONS_UI — Express GET /admin/positions redirects to Blade.
 * Phase 5 slice 16: USE_LARAVEL_ADMIN_DEPARTMENTS_UI — Express GET /admin/departments redirects to Blade.
 * Phase 5 slice 15: USE_LARAVEL_ADMIN_USERS_UI — Express GET /admin/users redirects to Blade.
 * Phase 5 slice 14: USE_LARAVEL_ADMIN_DASHBOARD_UI — Express GET /admin redirects to Blade.
 * Phase 5 slice 13: USE_LARAVEL_REPORTER_TICKET_FORM_UI — Express create/edit/preview
 * forms redirect to Laravel Blade; POST handlers remain on Express.
 */
module.exports = {
  /**
   * When true, Express POST /login verifies credentials via Laravel /v1/auth/verify.
   * Compose default true as of Phase 5 slice 3.
   */
  USE_LARAVEL_AUTH: process.env.USE_LARAVEL_AUTH === 'true',
  /**
   * When true and USE_LARAVEL_AUTH is on, fall back to Express store auth if Laravel
   * is unreachable (5xx/network). Default false — fail closed on Laravel outage.
   */
  USE_LARAVEL_AUTH_FALLBACK: process.env.USE_LARAVEL_AUTH_FALLBACK === 'true',
  /**
   * When true, Express GET /login redirects to Laravel Blade /laravel/login.
   * Compose default true as of Phase 5 slice 4.
   */
  USE_LARAVEL_LOGIN_UI: process.env.USE_LARAVEL_LOGIN_UI === 'true',
  /**
   * When true, Express GET /admin/profile redirects to Laravel Blade profile.
   * Compose default true as of Phase 5 slice 5.
   */
  USE_LARAVEL_PROFILE_UI: process.env.USE_LARAVEL_PROFILE_UI === 'true',
  /**
   * When true, Express GET /supervisor/profile redirects to Laravel Blade profile.
   * Compose default true as of Phase 5 slice 6.
   */
  USE_LARAVEL_REPORTER_PROFILE_UI: process.env.USE_LARAVEL_REPORTER_PROFILE_UI === 'true',
  /**
   * When true, Express GET /supervisor redirects to Laravel Blade dashboard.
   * Compose default true as of Phase 5 slice 7.
   */
  USE_LARAVEL_REPORTER_DASHBOARD_UI: process.env.USE_LARAVEL_REPORTER_DASHBOARD_UI === 'true',
  /**
   * When true, Express ticket list routes redirect to Laravel Blade lists.
   * Compose default true as of Phase 5 slice 8.
   */
  USE_LARAVEL_REPORTER_TICKETS_UI: process.env.USE_LARAVEL_REPORTER_TICKETS_UI === 'true',
  /**
   * When true, Express GET /supervisor/tickets/:ref redirects to Laravel Blade detail
   * (drafts/returned still go to Express edit). Compose default true as of Phase 5 slice 9.
   */
  USE_LARAVEL_REPORTER_TICKET_DETAIL_UI: process.env.USE_LARAVEL_REPORTER_TICKET_DETAIL_UI === 'true',
  /**
   * When true, Express GET /supervisor/notifications redirects to Laravel Blade.
   * Compose default true as of Phase 5 slice 10.
   */
  USE_LARAVEL_REPORTER_NOTIFICATIONS_UI: process.env.USE_LARAVEL_REPORTER_NOTIFICATIONS_UI === 'true',
  /**
   * When true, Express GET /supervisor/accomplishments redirects to Laravel Blade.
   * Compose default true as of Phase 5 slice 11.
   */
  USE_LARAVEL_REPORTER_ACCOMPLISHMENTS_UI: process.env.USE_LARAVEL_REPORTER_ACCOMPLISHMENTS_UI === 'true',
  /**
   * When true, Express GET /supervisor/actions redirects to Laravel Blade.
   * Compose default true as of Phase 5 slice 12.
   */
  USE_LARAVEL_REPORTER_ACTIONS_UI: process.env.USE_LARAVEL_REPORTER_ACTIONS_UI === 'true',
  /**
   * When true, Express GET ticket create/edit/preview forms redirect to Laravel Blade.
   * Compose default true as of Phase 5 slice 13.
   */
  USE_LARAVEL_REPORTER_TICKET_FORM_UI: process.env.USE_LARAVEL_REPORTER_TICKET_FORM_UI === 'true',
  /**
   * When true, Express GET /admin redirects to Laravel Blade dashboard.
   * Compose default true as of Phase 5 slice 14.
   */
  USE_LARAVEL_ADMIN_DASHBOARD_UI: process.env.USE_LARAVEL_ADMIN_DASHBOARD_UI === 'true',
  /**
   * When true, Express GET /admin/users (+ edit) redirects to Laravel Blade.
   * Compose default true as of Phase 5 slice 15. POSTs stay on Express.
   */
  USE_LARAVEL_ADMIN_USERS_UI: process.env.USE_LARAVEL_ADMIN_USERS_UI === 'true',
  /**
   * When true, Express GET /admin/departments (+ edit) redirects to Laravel Blade.
   * Compose default true as of Phase 5 slice 16. POSTs stay on Express.
   */
  USE_LARAVEL_ADMIN_DEPARTMENTS_UI: process.env.USE_LARAVEL_ADMIN_DEPARTMENTS_UI === 'true',
  /**
   * When true, Express GET /admin/positions (+ edit) redirects to Laravel Blade.
   * Compose default true as of Phase 5 slice 17. POSTs stay on Express.
   */
  USE_LARAVEL_ADMIN_POSITIONS_UI: process.env.USE_LARAVEL_ADMIN_POSITIONS_UI === 'true',
  /**
   * When true, Express GET /admin/tickets redirects to Laravel Blade.
   * Compose default true as of Phase 5 slice 18. Delete POST stays on Express.
   */
  USE_LARAVEL_ADMIN_TICKETS_UI: process.env.USE_LARAVEL_ADMIN_TICKETS_UI === 'true',
  /**
   * When true, Express GET /admin/tickets/:ref redirects to Laravel Blade.
   * Compose default true as of Phase 5 slice 19. Detail view stays read-only.
   */
  USE_LARAVEL_ADMIN_TICKET_DETAIL_UI: process.env.USE_LARAVEL_ADMIN_TICKET_DETAIL_UI === 'true',
  /**
   * When true, Express GET /admin/audit-logs redirects to Laravel Blade.
   * Compose default true as of Phase 5 slice 20.
   */
  USE_LARAVEL_ADMIN_AUDIT_LOGS_UI: process.env.USE_LARAVEL_ADMIN_AUDIT_LOGS_UI === 'true',
  /**
   * When true, Express GET /admin/settings redirects to Laravel Blade.
   * Compose default true as of Phase 5 slice 21. Save/reset POSTs stay on Express.
   */
  USE_LARAVEL_ADMIN_SETTINGS_UI: process.env.USE_LARAVEL_ADMIN_SETTINGS_UI === 'true',
  /**
   * When true, Express GET /dept redirects to Laravel Blade.
   * Compose default true as of Phase 5 slice 22. Queues/detail stay on Express.
   */
  USE_LARAVEL_DEPT_DASHBOARD_UI: process.env.USE_LARAVEL_DEPT_DASHBOARD_UI === 'true',
  /**
   * When true, Express GET /dept/{inbox,active,drafts,returned,overdue,closure,tickets}
   * redirects to Laravel Blade. Compose default true as of Phase 5 slice 23.
   * Ticket detail stays on Express.
   */
  USE_LARAVEL_DEPT_QUEUES_UI: process.env.USE_LARAVEL_DEPT_QUEUES_UI === 'true',
  /**
   * When true, Express GET /dept/tickets/:ref redirects to Laravel Blade.
   * Compose default true as of Phase 5 slice 24. Ownership/action POSTs stay on Express.
   */
  USE_LARAVEL_DEPT_TICKET_DETAIL_UI: process.env.USE_LARAVEL_DEPT_TICKET_DETAIL_UI === 'true',
  /**
   * When true, Express GET /officer redirects to Laravel Blade.
   * Compose default true as of Phase 5 slice 25. Queues/detail stay on Express.
   */
  USE_LARAVEL_OFFICER_DASHBOARD_UI: process.env.USE_LARAVEL_OFFICER_DASHBOARD_UI === 'true',
  /**
   * When true, Express GET /officer/{tickets,overdue,monitoring,action-plans} redirect to Laravel Blade.
   * Compose default true as of Phase 5 slice 26. Ticket detail stays on Express.
   */
  USE_LARAVEL_OFFICER_QUEUES_UI: process.env.USE_LARAVEL_OFFICER_QUEUES_UI === 'true',
  /**
   * When true, Express GET /officer/tickets/:ref redirects to Laravel Blade.
   * Compose default true as of Phase 5 slice 27. Thread/reopen POSTs stay on Express.
   */
  USE_LARAVEL_OFFICER_TICKET_DETAIL_UI: process.env.USE_LARAVEL_OFFICER_TICKET_DETAIL_UI === 'true',
  /**
   * When true, Express GET /officer/{ai-review,review,final-validation} redirect to Laravel aliases.
   * Compose default true as of Phase 6 slice 5.
   */
  USE_LARAVEL_OFFICER_ALIASES_UI: process.env.USE_LARAVEL_OFFICER_ALIASES_UI === 'true',
  /**
   * When true, Express GET /executive redirects to Laravel Blade.
   * Compose default true as of Phase 5 slice 28.
   */
  USE_LARAVEL_EXECUTIVE_DASHBOARD_UI: process.env.USE_LARAVEL_EXECUTIVE_DASHBOARD_UI === 'true',
  /**
   * When true, Express GET /executive/{heatmap,reports,trends,statistics,departments,register,critical}
   * redirect to Laravel Blade. Compose default true as of Phase 6 slice 3.
   */
  USE_LARAVEL_EXECUTIVE_PAGES_UI: process.env.USE_LARAVEL_EXECUTIVE_PAGES_UI === 'true',
  /**
   * When true, Express GET /executive/tickets/:ref redirects to Laravel Blade.
   * Compose default true as of Phase 6 slice 4. Comment POSTs stay on Express.
   */
  USE_LARAVEL_EXECUTIVE_TICKET_DETAIL_UI: process.env.USE_LARAVEL_EXECUTIVE_TICKET_DETAIL_UI === 'true',
  /**
   * When true, Express GET /president redirects to Laravel Blade.
   * Compose default true as of Phase 5 slice 29.
   */
  USE_LARAVEL_PRESIDENT_DASHBOARD_UI: process.env.USE_LARAVEL_PRESIDENT_DASHBOARD_UI === 'true',
  /**
   * When true, Express GET /president/{pending,high,critical,trends} redirect to Blade.
   * Compose default true as of Phase 5 slice 30.
   */
  USE_LARAVEL_PRESIDENT_QUEUES_UI: process.env.USE_LARAVEL_PRESIDENT_QUEUES_UI === 'true',
  /**
   * When true, Express GET /president/tickets/:ref redirects to Blade.
   * Compose default true as of Phase 5 slice 31. Decision/comment POSTs stay on Express.
   */
  USE_LARAVEL_PRESIDENT_TICKET_DETAIL_UI: process.env.USE_LARAVEL_PRESIDENT_TICKET_DETAIL_UI === 'true',
  /**
   * Observability only: edge nginx `location = /` proxies to Laravel when true.
   * Compose default true as of Phase 6 slice 1. Soak sets false (Laravel `/` still
   * redirects guests to Express `/login`).
   */
  USE_LARAVEL_EDGE_ROOT: process.env.USE_LARAVEL_EDGE_ROOT === 'true',
  /**
   * Observability: unprefixed Blade GETs (/login, /admin, …) via nginx when true.
   * Compose default true as of Phase 6 slice 2.
   */
  USE_LARAVEL_EDGE_UI: process.env.USE_LARAVEL_EDGE_UI === 'true',
  /**
   * When true, Express GET /dashboard redirects to Laravel Blade.
   * Compose default true as of Phase 6 slice 6.
   */
  USE_LARAVEL_DASHBOARD_UI: process.env.USE_LARAVEL_DASHBOARD_UI === 'true',
  /**
   * When true, Express POST /admin/departments* redirect to Laravel Blade mutations.
   * Compose default true as of Phase 7 slice 1.
   */
  USE_LARAVEL_ADMIN_DEPT_MUTATIONS: process.env.USE_LARAVEL_ADMIN_DEPT_MUTATIONS === 'true',
  /**
   * When true, Express POST /admin/positions* redirect to Laravel Blade mutations.
   * Compose default true as of Phase 7 slice 2.
   */
  USE_LARAVEL_ADMIN_POS_MUTATIONS: process.env.USE_LARAVEL_ADMIN_POS_MUTATIONS === 'true',
  /**
   * When true, Express POST /admin/users* redirect to Laravel Blade mutations.
   * Compose default true as of Phase 7 slice 3.
   */
  USE_LARAVEL_ADMIN_USER_MUTATIONS: process.env.USE_LARAVEL_ADMIN_USER_MUTATIONS === 'true',
  /**
   * When true, Express POST /admin/settings* redirect to Laravel Blade mutations.
   * Compose default true as of Phase 7 slice 4.
   */
  USE_LARAVEL_ADMIN_SETTINGS_MUTATIONS: process.env.USE_LARAVEL_ADMIN_SETTINGS_MUTATIONS === 'true',
  /**
   * When true, Express POST /admin/tickets/:ref/delete redirects to Laravel Blade.
   * Compose default true as of Phase 7 slice 5.
   */
  USE_LARAVEL_ADMIN_TICKET_MUTATIONS: process.env.USE_LARAVEL_ADMIN_TICKET_MUTATIONS === 'true',
  /**
   * When true, Express POST /dept/tickets/:ref/{accept,reject,return,reassign,action-plan,close,comment,documents}
   * redirect to Laravel Blade. Compose default true as of Phase 7 slice 6; comment as of slice 13; documents as of Phase 8 slice 3.
   */
  USE_LARAVEL_DEPT_TICKET_MUTATIONS: process.env.USE_LARAVEL_DEPT_TICKET_MUTATIONS === 'true',
  /**
   * When true, Express POST /supervisor/tickets/:ref/delete and
   * /supervisor/tickets/new/preview/:ref/{save,submit} redirect to Laravel Blade.
   * Compose default true as of Phase 7 slice 7.
   */
  USE_LARAVEL_REPORTER_TICKET_MUTATIONS: process.env.USE_LARAVEL_REPORTER_TICKET_MUTATIONS === 'true',
  /**
   * When true, Express POST /supervisor/tickets/new/preview,
   * /supervisor/tickets/:ref/edit, /:ref/evidence, and /:ref/accomplishment
   * redirect to Laravel Blade. Compose default true as of Phase 8 slice 1; evidence/accomplishment as of slice 2.
   */
  USE_LARAVEL_REPORTER_UPLOAD_MUTATIONS: process.env.USE_LARAVEL_REPORTER_UPLOAD_MUTATIONS === 'true',
  /**
   * When true, Express POST /officer/tickets/:ref/reopen and
   * /officer/tickets/:ref/thread-comment redirect to Laravel Blade.
   * Compose default true as of Phase 7 slice 8; thread-comment as of slice 11.
   */
  USE_LARAVEL_OFFICER_TICKET_MUTATIONS: process.env.USE_LARAVEL_OFFICER_TICKET_MUTATIONS === 'true',
  /**
   * When true, Express POST /president/tickets/:ref/decision and
   * /president/tickets/:ref/comment redirect to Laravel Blade.
   * Compose default true as of Phase 7 slice 9; comment as of slice 12.
   */
  USE_LARAVEL_PRESIDENT_TICKET_MUTATIONS: process.env.USE_LARAVEL_PRESIDENT_TICKET_MUTATIONS === 'true',
  /**
   * When true, Express POST /executive/tickets/:ref/comment redirects to Laravel Blade.
   * Compose default true as of Phase 7 slice 10. File uploads stay Express.
   */
  USE_LARAVEL_EXECUTIVE_TICKET_MUTATIONS: process.env.USE_LARAVEL_EXECUTIVE_TICKET_MUTATIONS === 'true',
  /** When true (future), admin org reads may use Laravel /api/v1 — unused today. */
  USE_LARAVEL_ORG: process.env.USE_LARAVEL_ORG === 'true',
  /**
   * When true, Express mirrors ticket workflow to Laravel AND routes attachment
   * upload/download through Laravel APIs. Compose default true as of Phase 5 slice 1.
   */
  USE_LARAVEL_API: process.env.USE_LARAVEL_API === 'true',
};
