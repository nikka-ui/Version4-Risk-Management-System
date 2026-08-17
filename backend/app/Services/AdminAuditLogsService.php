<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\AuditActions;
use Illuminate\Database\Eloquent\Builder;

/**
 * Phase 10 slice 1: admin audit list/export from Postgres (store.json is dual-write only).
 */
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
        $filters = [
            'q' => $q ?? '',
            'date' => $date ?? '',
            'user' => $user ?? '',
            'action' => $action ?? '',
            'module' => $module ?? '',
        ];

        $query = AuditLog::query()->orderByDesc('occurred_at');
        $this->applyFilters($query, $filters);

        $logs = $query
            ->limit(max(1, $limit))
            ->get()
            ->map(fn (AuditLog $row) => $row->toStoreArray())
            ->all();

        return [
            'logs' => $logs,
            'options' => $this->options(),
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
     * @param  Builder<AuditLog>  $query
     * @param  array<string, string>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $needle = mb_strtolower(trim($filters['q']));
        if ($needle !== '') {
            $query->where(function (Builder $inner) use ($needle): void {
                $inner->whereRaw('LOWER(COALESCE(description, \'\')) LIKE ?', ['%'.$needle.'%'])
                    ->orWhereRaw('LOWER(COALESCE(username, \'\')) LIKE ?', ['%'.$needle.'%'])
                    ->orWhereRaw('LOWER(COALESCE(module, \'\')) LIKE ?', ['%'.$needle.'%'])
                    ->orWhereRaw('LOWER(COALESCE(action, \'\')) LIKE ?', ['%'.$needle.'%']);
                foreach (AuditActions::matchingActions($needle) as $action) {
                    $inner->orWhere('action', $action);
                }
            });
        }

        if ($filters['date'] !== '') {
            $day = mb_substr($filters['date'], 0, 10);
            $query->whereDate('occurred_at', $day);
        }

        if ($filters['user'] !== '') {
            $uq = mb_strtolower(trim($filters['user']));
            $query->where(function (Builder $inner) use ($uq): void {
                $inner->whereRaw('LOWER(COALESCE(username, \'\')) LIKE ?', ['%'.$uq.'%'])
                    ->orWhereRaw('LOWER(COALESCE(role_label, \'\')) LIKE ?', ['%'.$uq.'%']);
            });
        }

        if ($filters['action'] !== '') {
            $aq = mb_strtolower(trim($filters['action']));
            $query->where(function (Builder $inner) use ($aq): void {
                $inner->whereRaw('LOWER(COALESCE(action, \'\')) LIKE ?', ['%'.$aq.'%']);
                foreach (AuditActions::matchingActions($aq) as $action) {
                    $inner->orWhere('action', $action);
                }
            });
        }

        if ($filters['module'] !== '') {
            $mq = mb_strtolower(trim($filters['module']));
            $query->whereRaw('LOWER(COALESCE(module, \'\')) LIKE ?', ['%'.$mq.'%']);
        }
    }

    /**
     * @return array{users: list<string>, actions: list<string>, modules: list<string>}
     */
    private function options(): array
    {
        $users = AuditLog::query()
            ->whereNotNull('username')
            ->where('username', '!=', '')
            ->distinct()
            ->orderBy('username')
            ->pluck('username')
            ->map(fn ($v) => (string) $v)
            ->all();

        $actions = AuditLog::query()
            ->whereNotNull('action')
            ->where('action', '!=', '')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->map(fn ($v) => (string) $v)
            ->all();

        $modules = AuditLog::query()
            ->whereNotNull('module')
            ->where('module', '!=', '')
            ->distinct()
            ->orderBy('module')
            ->pluck('module')
            ->map(fn ($v) => (string) $v)
            ->all();

        return [
            'users' => $users,
            'actions' => $actions,
            'modules' => $modules,
        ];
    }
}
