/**
 * Feature flags for gradual Laravel migration.
 *
 * Phase 3: all flags default OFF and are NOT wired into login, sessions, admin
 * UI, ticket workflow, or role guards. Express remains the browser entry.
 */
module.exports = {
  /** When true (future), Express may call Laravel for identity — unused today. */
  USE_LARAVEL_AUTH: process.env.USE_LARAVEL_AUTH === 'true',
  /** When true (future), admin org reads may use Laravel /api/v1 — unused today. */
  USE_LARAVEL_ORG: process.env.USE_LARAVEL_ORG === 'true',
  /** When true (future), Express may proxy ticket APIs to Laravel — unused today. */
  USE_LARAVEL_API: process.env.USE_LARAVEL_API === 'true',
};
