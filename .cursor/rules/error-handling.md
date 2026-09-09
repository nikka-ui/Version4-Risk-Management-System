---
description: Error handling and safe logging — no silent failures, no sensitive logs
alwaysApply: true
---

# Error Handling

## Required handling

- Any code that can fail — HTTP/API calls, DB queries, file/S3 I/O, mail, queue jobs, AI service calls — must handle errors explicitly. **No empty `catch` blocks and no silent failures.**
- Prefer Laravel exceptions + HTTP exception helpers (`abort`, `throw ValidationException`, domain exceptions) so failures surface as correct status codes.
- Controllers/Services: catch only when you can recover, translate to a user-safe response, or add context before rethrowing.
- Queue/console commands: fail visibly; use retries/`--tries` intentionally; log and exit non-zero on unrecoverable errors.

## Logging

- Log with enough context to debug: operation name, entity IDs (ticket reference, user id), and exception class/message.
- Use `Log::error` / `Log::warning` (or reportable exceptions) — not `dd`/`dump` in committed code.
- **Never log** passwords, OTPs, API tokens, `Authorization` headers, session cookies, full `.env` values, or unnecessary PII (e.g. raw national IDs). Prefer opaque IDs over emails when possible.
- Do not return stack traces or internal paths to end users in production (`APP_DEBUG=false`).

## Examples

```php
// ❌ BAD
try {
    $this->ai->analyze($payload);
} catch (\Throwable $e) {
}

// ✅ GOOD
try {
    $this->ai->analyze($payload);
} catch (\Throwable $e) {
    Log::error('AI analysis failed', [
        'ticket_id' => $ticketId,
        'exception' => $e::class,
        'message' => $e->getMessage(),
    ]);
    throw $e;
}
```
