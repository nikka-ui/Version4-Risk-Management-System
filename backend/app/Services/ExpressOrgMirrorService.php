<?php

namespace App\Services;

/**
 * Optional store.json dual-write for org/ticket mirrors.
 * Phase 10 slice 3: dual-write defaults OFF; Postgres is sole live SoT.
 * When mirrors are off, audit rows still go to Postgres via AuditLogService.
 */
class ExpressOrgMirrorService
{
    /**
     * @param  array<string, mixed>  $department
     * @param  array<string, mixed>|null  $audit
     * @param  array<string, mixed>|null  $notification
     */
    public function syncDepartment(string $op, array $department, ?array $audit = null, ?array $notification = null): void
    {
        if (! $this->writeLocalOrg(fn (StoreJsonOrgMirror $mirror) => $mirror->applyDepartment($op, $department, $audit, $notification))) {
            $this->persistAudit($audit);
        }
    }

    /**
     * @param  array<string, mixed>  $position
     * @param  array<string, mixed>|null  $audit
     */
    public function syncPosition(string $op, array $position, ?array $audit = null): void
    {
        if (! $this->writeLocalOrg(fn (StoreJsonOrgMirror $mirror) => $mirror->applyPosition($op, $position, $audit))) {
            $this->persistAudit($audit);
        }
    }

    /**
     * @param  array<string, mixed>  $user
     * @param  array<string, mixed>|null  $audit
     * @param  array<string, mixed>|null  $notification
     * @param  array<string, mixed>|null  $credential
     */
    public function syncUser(string $op, array $user, ?array $audit = null, ?array $notification = null, ?array $credential = null): void
    {
        if (! $this->writeLocalOrg(fn (StoreJsonOrgMirror $mirror) => $mirror->applyUser($op, $user, $audit, $notification, $credential))) {
            $this->persistAudit($audit);
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>|null  $audit
     */
    public function syncSettings(array $settings, ?array $audit = null): void
    {
        if (! $this->writeLocalOrg(fn (StoreJsonOrgMirror $mirror) => $mirror->applySettings($settings, $audit))) {
            $this->persistAudit($audit);
        }
    }

    /**
     * @param  array<string, mixed>  $ticket
     * @param  array<string, mixed>|null  $audit
     * @param  array<string, mixed>|null  $notification
     */
    public function syncTicketSoftDelete(array $ticket, ?array $audit = null, ?array $notification = null): void
    {
        if (! $this->writeLocalTicket(fn (StoreJsonTicketMirror $mirror) => $mirror->softDelete($ticket, $audit, $notification))) {
            $this->persistAudit($audit);
        }
    }

    /**
     * @param  array<string, mixed>  $ticket
     */
    public function syncTicket(array $ticket): void
    {
        $this->writeLocalTicket(fn (StoreJsonTicketMirror $mirror) => $mirror->upsert($ticket));
    }

    public function syncTicketDeleteDraft(string $reference): void
    {
        $this->writeLocalTicket(fn (StoreJsonTicketMirror $mirror) => $mirror->deleteDraft($reference));
    }

    /**
     * @param  array<string, mixed>|null  $audit
     */
    private function persistAudit(?array $audit): void
    {
        if (! is_array($audit)) {
            return;
        }

        try {
            app(AuditLogService::class)->record($audit);
        } catch (\Throwable $e) {
            logger()->warning('audit_logs write failed: '.$e->getMessage());
        }
    }

    /**
     * @param  callable(StoreJsonOrgMirror): mixed  $writer
     */
    private function writeLocalOrg(callable $writer): bool
    {
        if (! (bool) config('rms.store_json_org_mirror', false)) {
            return false;
        }

        try {
            $writer(app(StoreJsonOrgMirror::class));

            return true;
        } catch (\Throwable $e) {
            logger()->warning('store.json org mirror failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * @param  callable(StoreJsonTicketMirror): mixed  $writer
     */
    private function writeLocalTicket(callable $writer): bool
    {
        if (! (bool) config('rms.store_json_ticket_mirror', false)) {
            return false;
        }

        try {
            $writer(app(StoreJsonTicketMirror::class));

            return true;
        } catch (\Throwable $e) {
            logger()->warning('store.json ticket mirror failed: '.$e->getMessage());

            return false;
        }
    }
}
