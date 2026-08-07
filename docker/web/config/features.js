/**
 * Feature flags for gradual Laravel migration.
 *
 * Phase 3 slice 6: dept return/reassign/close + president decision APIs + Express mirror when
 * USE_LARAVEL_API=true. Default remains false — live UI uses Express only.
 */
module.exports = {
  /** When true (future), Express may call Laravel for identity — unused today. */
  USE_LARAVEL_AUTH: process.env.USE_LARAVEL_AUTH === 'true',
  /** When true (future), admin org reads may use Laravel /api/v1 — unused today. */
  USE_LARAVEL_ORG: process.env.USE_LARAVEL_ORG === 'true',
  /**
   * When true, Express draft/submit/dept actions also mirror to Laravel after store.json.
   * Default false — live app behavior unchanged.
   */
  USE_LARAVEL_API: process.env.USE_LARAVEL_API === 'true',
};
