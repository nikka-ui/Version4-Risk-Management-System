<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Phase 7 slice 1–13 + Phase 8 slice 1–5: fire-and-forget Laravel → Express store.json org/user/settings mirror.
 * Phase 9 slice 5–6: ticket/org dual-write store.json in-process when internal flags are on.
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
        if ($this->writeLocalOrg(fn (StoreJsonOrgMirror $mirror) => $mirror->applyDepartment($op, $department, $audit, $notification))) {
            return;
        }

        $token = (string) config('rms.internal_service_token', '');
        $base = rtrim((string) config('rms.express_web_url', ''), '/');
        if ($token === '' || $base === '') {
            return;
        }

        try {
            Http::timeout(3)
                ->acceptJson()
                ->withHeaders(['X-RMS-Service-Token' => $token])
                ->post($base.'/internal/org/departments', [
                    'op' => $op,
                    'department' => $department,
                    'audit' => $audit,
                    'notification' => $notification,
                ]);
        } catch (\Throwable $e) {
            logger()->warning('express org mirror failed: '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $position
     * @param  array<string, mixed>|null  $audit
     */
    public function syncPosition(string $op, array $position, ?array $audit = null): void
    {
        if ($this->writeLocalOrg(fn (StoreJsonOrgMirror $mirror) => $mirror->applyPosition($op, $position, $audit))) {
            return;
        }

        $token = (string) config('rms.internal_service_token', '');
        $base = rtrim((string) config('rms.express_web_url', ''), '/');
        if ($token === '' || $base === '') {
            return;
        }

        try {
            Http::timeout(3)
                ->acceptJson()
                ->withHeaders(['X-RMS-Service-Token' => $token])
                ->post($base.'/internal/org/positions', [
                    'op' => $op,
                    'position' => $position,
                    'audit' => $audit,
                ]);
        } catch (\Throwable $e) {
            logger()->warning('express org mirror failed: '.$e->getMessage());
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
        if ($this->writeLocalOrg(fn (StoreJsonOrgMirror $mirror) => $mirror->applyUser($op, $user, $audit, $notification, $credential))) {
            return;
        }

        $token = (string) config('rms.internal_service_token', '');
        $base = rtrim((string) config('rms.express_web_url', ''), '/');
        if ($token === '' || $base === '') {
            return;
        }

        try {
            Http::timeout(3)
                ->acceptJson()
                ->withHeaders(['X-RMS-Service-Token' => $token])
                ->post($base.'/internal/org/users', [
                    'op' => $op,
                    'user' => $user,
                    'audit' => $audit,
                    'notification' => $notification,
                    'credential' => $credential,
                ]);
        } catch (\Throwable $e) {
            logger()->warning('express org mirror failed: '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>|null  $audit
     */
    public function syncSettings(array $settings, ?array $audit = null): void
    {
        if ($this->writeLocalOrg(fn (StoreJsonOrgMirror $mirror) => $mirror->applySettings($settings, $audit))) {
            return;
        }

        $token = (string) config('rms.internal_service_token', '');
        $base = rtrim((string) config('rms.express_web_url', ''), '/');
        if ($token === '' || $base === '') {
            return;
        }

        try {
            Http::timeout(3)
                ->acceptJson()
                ->withHeaders(['X-RMS-Service-Token' => $token])
                ->post($base.'/internal/org/settings', [
                    'settings' => $settings,
                    'audit' => $audit,
                ]);
        } catch (\Throwable $e) {
            logger()->warning('express org mirror failed: '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $ticket
     * @param  array<string, mixed>|null  $audit
     * @param  array<string, mixed>|null  $notification
     */
    public function syncTicketSoftDelete(array $ticket, ?array $audit = null, ?array $notification = null): void
    {
        if ($this->writeLocalTicket(fn (StoreJsonTicketMirror $mirror) => $mirror->softDelete($ticket, $audit, $notification))) {
            return;
        }

        $token = (string) config('rms.internal_service_token', '');
        $base = rtrim((string) config('rms.express_web_url', ''), '/');
        if ($token === '' || $base === '') {
            return;
        }

        try {
            Http::timeout(3)
                ->acceptJson()
                ->withHeaders(['X-RMS-Service-Token' => $token])
                ->post($base.'/internal/tickets/soft-delete', [
                    'ticket' => $ticket,
                    'audit' => $audit,
                    'notification' => $notification,
                ]);
        } catch (\Throwable $e) {
            logger()->warning('express ticket mirror failed: '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $ticket
     */
    public function syncTicket(array $ticket): void
    {
        if ($this->writeLocalTicket(fn (StoreJsonTicketMirror $mirror) => $mirror->upsert($ticket))) {
            return;
        }

        $token = (string) config('rms.internal_service_token', '');
        $base = rtrim((string) config('rms.express_web_url', ''), '/');
        if ($token === '' || $base === '') {
            return;
        }

        try {
            Http::timeout(3)
                ->acceptJson()
                ->withHeaders(['X-RMS-Service-Token' => $token])
                ->post($base.'/internal/tickets/upsert', [
                    'ticket' => $ticket,
                ]);
        } catch (\Throwable $e) {
            logger()->warning('express ticket mirror failed: '.$e->getMessage());
        }
    }

    public function syncTicketDeleteDraft(string $reference): void
    {
        $token = (string) config('rms.internal_service_token', '');
        $base = rtrim((string) config('rms.express_web_url', ''), '/');
        if ($token !== '' && $base !== '' && $reference !== '') {
            try {
                Http::timeout(3)
                    ->acceptJson()
                    ->withHeaders(['X-RMS-Service-Token' => $token])
                    ->post($base.'/internal/tickets/delete-draft', [
                        'ticket' => ['reference' => $reference],
                    ]);
            } catch (\Throwable $e) {
                logger()->warning('express ticket mirror failed: '.$e->getMessage());
            }
        }

        $this->writeLocalTicket(fn (StoreJsonTicketMirror $mirror) => $mirror->deleteDraft($reference));
    }

    /**
     * @param  callable(StoreJsonOrgMirror): mixed  $writer
     */
    private function writeLocalOrg(callable $writer): bool
    {
        if (! (bool) config('rms.store_json_org_mirror', true)) {
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
        if (! (bool) config('rms.store_json_ticket_mirror', true)) {
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
