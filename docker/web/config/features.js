/**
 * Feature flags for gradual Laravel migration.
 *
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
  /** When true (future), admin org reads may use Laravel /api/v1 — unused today. */
  USE_LARAVEL_ORG: process.env.USE_LARAVEL_ORG === 'true',
  /**
   * When true, Express mirrors ticket workflow to Laravel AND routes attachment
   * upload/download through Laravel APIs. Compose default true as of Phase 5 slice 1.
   */
  USE_LARAVEL_API: process.env.USE_LARAVEL_API === 'true',
};
