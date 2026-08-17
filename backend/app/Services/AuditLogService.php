<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Phase 10 slice 1: persist admin audit rows to Postgres (alongside store.json dual-write).
 */
class AuditLogService
{
    /**
     * @param  array<string, mixed>  $audit
     */
    public function record(array $audit): AuditLog
    {
        $id = trim((string) ($audit['id'] ?? ''));
        if ($id === '') {
            $id = 'alog-'.now()->getTimestampMs().'-'.Str::lower(Str::random(4));
        }

        $at = $audit['at'] ?? null;
        $occurredAt = $at ? Carbon::parse((string) $at) : now();

        $roleLabel = $audit['roleLabel'] ?? $audit['role_label'] ?? null;
        $targetUser = $audit['targetUser'] ?? $audit['target_user'] ?? null;

        $known = [
            'id', 'at', 'username', 'role', 'roleLabel', 'role_label', 'action', 'module',
            'description', 'ip', 'device', 'browser', 'targetUser', 'target_user',
        ];
        $meta = [];
        foreach ($audit as $key => $value) {
            if (! in_array($key, $known, true)) {
                $meta[$key] = $value;
            }
        }

        return AuditLog::query()->updateOrCreate(
            ['id' => $id],
            [
                'occurred_at' => $occurredAt,
                'username' => isset($audit['username']) ? (string) $audit['username'] : null,
                'role' => isset($audit['role']) ? (string) $audit['role'] : null,
                'role_label' => $roleLabel !== null ? (string) $roleLabel : null,
                'action' => isset($audit['action']) ? (string) $audit['action'] : null,
                'module' => isset($audit['module']) ? (string) $audit['module'] : null,
                'description' => isset($audit['description']) ? (string) $audit['description'] : null,
                'ip' => isset($audit['ip']) ? (string) $audit['ip'] : null,
                'device' => isset($audit['device']) ? (string) $audit['device'] : null,
                'browser' => isset($audit['browser']) ? (string) $audit['browser'] : null,
                'target_user' => $targetUser !== null ? (string) $targetUser : null,
                'meta' => $meta === [] ? null : $meta,
            ]
        );
    }
}
