<?php

namespace App\Services;

use App\Support\AuditActions;

class AdminAuditLogsService
{
    /**
     * @return array{
     *   logs: list<array<string,mixed>>,
     *   options: array{users: list<string>, actions: list<string>, modules: list<string>},
     *   filters: array<string, string>,
     * }
     */
    public function list(
        ?string $q,
        ?string $date,
        ?string $user,
        ?string $action,
        ?string $module,
        int $limit = 300,
    ): array {
        $storePath = (string) config('rms.store_json_path');
        $raw = @file_get_contents($storePath);
        $data = $raw ? json_decode($raw, true) : [];

        $auditLogs = is_array($data['auditLogs'] ?? null) ? (array) $data['auditLogs'] : [];

        $needle = $q !== null ? mb_strtolower(trim($q)) : '';
        $filters = [
            'q' => $q ?? '',
            'date' => $date ?? '',
            'user' => $user ?? '',
            'action' => $action ?? '',
            'module' => $module ?? '',
        ];

        if ($needle !== '') {
            $auditLogs = array_values(array_filter($auditLogs, function (array $l) use ($needle): bool {
                $hay = mb_strtolower(trim((string) ($l['description'] ?? ''))) ?: '';
                $hay .= ' ' . mb_strtolower(trim((string) ($l['username'] ?? ''))) ;
                $hay .= ' ' . mb_strtolower(trim((string) ($l['module'] ?? ''))) ;
                $hay .= ' ' . mb_strtolower(trim((string) ($l['action'] ?? ''))) ;
                $hay .= ' ' . mb_strtolower(trim(AuditActions::label($l['action'] ?? ''))) ;
                return str_contains($hay, $needle);
            }));
        }

        if (! empty($filters['date'])) {
            $day = mb_substr($filters['date'], 0, 10);
            $auditLogs = array_values(array_filter($auditLogs, function (array $l) use ($day): bool {
                return str_starts_with((string) ($l['at'] ?? ''), $day);
            }));
        }

        if (! empty($filters['user'])) {
            $uq = mb_strtolower(trim($filters['user']));
            $auditLogs = array_values(array_filter($auditLogs, function (array $l) use ($uq): bool {
                $u1 = mb_strtolower(trim((string) ($l['username'] ?? '')));
                $u2 = mb_strtolower(trim((string) ($l['roleLabel'] ?? '')));
                return str_contains($u1, $uq) || str_contains($u2, $uq);
            }));
        }

        if (! empty($filters['action'])) {
            $aq = mb_strtolower(trim($filters['action']));
            $auditLogs = array_values(array_filter($auditLogs, function (array $l) use ($aq): bool {
                $a = mb_strtolower(trim((string) ($l['action'] ?? '')));
                $label = mb_strtolower(trim(AuditActions::label($l['action'] ?? '')));
                return str_contains($a, $aq) || str_contains($label, $aq);
            }));
        }

        if (! empty($filters['module'])) {
            $mq = mb_strtolower(trim($filters['module']));
            $auditLogs = array_values(array_filter($auditLogs, function (array $l) use ($mq): bool {
                $m = mb_strtolower(trim((string) ($l['module'] ?? '')));
                return str_contains($m, $mq);
            }));
        }

        // Options used to populate selects in the filter bar.
        $options = $this->options($data['auditLogs'] ?? []);

        usort($auditLogs, fn (array $a, array $b) => strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? '')));
        $auditLogs = array_slice($auditLogs, 0, max(1, $limit));

        // Ensure roleLabel exists for the view.
        $auditLogs = array_map(function (array $l): array {
            if (empty($l['roleLabel']) && ! empty($l['role'])) {
                $l['roleLabel'] = (string) $l['role'];
            }
            return $l;
        }, $auditLogs);

        return [
            'logs' => $auditLogs,
            'options' => $options,
            'filters' => $filters,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $logs
     */
    public function toCsv(array $logs): string
    {
        $header = 'Date,User,Role,Action,Module,Description,IP,Device,Browser';
        $rows = array_map(function (array $l): string {
            $cells = [
                $l['at'] ?? '',
                $l['username'] ?? '',
                $l['roleLabel'] ?? '',
                $l['action'] ?? '',
                $l['module'] ?? '',
                $l['description'] ?? '',
                $l['ip'] ?? '',
                $l['device'] ?? '',
                $l['browser'] ?? '',
            ];

            return implode(',', array_map(function (mixed $v): string {
                return '"'.str_replace('"', '""', (string) $v).'"';
            }, $cells));
        }, $logs);

        return $header."\n".implode("\n", $rows);
    }

    /**
     * @param mixed $auditLogs
     * @return array{users: list<string>, actions: list<string>, modules: list<string>}
     */
    private function options(mixed $auditLogs): array
    {
        $auditLogs = is_array($auditLogs) ? $auditLogs : [];

        $users = [];
        $actions = [];
        $modules = [];

        foreach ($auditLogs as $l) {
            if (! is_array($l)) continue;
            if (! empty($l['username'])) $users[] = (string) $l['username'];
            if (! empty($l['action'])) $actions[] = (string) $l['action'];
            if (! empty($l['module'])) $modules[] = (string) $l['module'];
        }

        $users = array_values(array_unique($users));
        $actions = array_values(array_unique($actions));
        $modules = array_values(array_unique($modules));

        sort($users);
        sort($actions);
        sort($modules);

        return [
            'users' => $users,
            'actions' => $actions,
            'modules' => $modules,
        ];
    }
}

