<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Position;
use App\Support\OrgSeed;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Idempotent import of Express store.json departments and positions into Postgres.
 * Does not modify store.json; Express remains source of truth for the live app.
 */
class ImportOrgFromStore extends Command
{
    protected $signature = 'rms:import-org
                            {--path= : Path to store.json (default: STORE_JSON_PATH / config)}
                            {--dry-run : Parse and report without writing}';

    protected $description = 'Import departments and positions from Express store.json (idempotent upsert by external id)';

    public function handle(): int
    {
        $path = $this->option('path') ?: config('rms.store_json_path');
        $path = (string) $path;

        if ($path === '' || ! is_readable($path)) {
            $this->error("store.json not readable at: {$path}");

            return self::FAILURE;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            $this->error("Failed to read: {$path}");

            return self::FAILURE;
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            $this->error('Invalid store.json.');

            return self::FAILURE;
        }

        $now = now()->toIso8601String();
        $departments = $this->normalizeDepartments($data['departments'] ?? null, $now);
        $positions = $this->normalizePositions($data['positions'] ?? null, $now);
        $dryRun = (bool) $this->option('dry-run');

        if ($departments === [] && $positions === []) {
            $this->warn('No departments or positions found; using Express seed defaults.');
            $departments = OrgSeed::departments($now);
            $positions = OrgSeed::positions($now);
        } elseif ($departments === []) {
            $this->warn('No departments in store.json; using Express seed defaults.');
            $departments = OrgSeed::departments($now);
        } elseif ($positions === []) {
            $this->warn('No positions in store.json; using Express seed defaults.');
            $positions = OrgSeed::positions($now);
        }

        $deptStats = $this->importDepartments($departments, $dryRun);
        $posStats = $this->importPositions($positions, $dryRun);

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}Departments: created={$deptStats['created']} updated={$deptStats['updated']} skipped={$deptStats['skipped']}");
        $this->info("{$prefix}Positions: created={$posStats['created']} updated={$posStats['updated']} skipped={$posStats['skipped']}");
        $this->line('Express store.json was not modified. Admin UI remains on Express until USE_LARAVEL_ORG is enabled.');

        return self::SUCCESS;
    }

    /**
     * @param  mixed  $rows
     * @return list<array<string, mixed>>
     */
    private function normalizeDepartments(mixed $rows, string $fallbackNow): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $externalId = trim((string) ($row['id'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $code = trim((string) ($row['code'] ?? ''));
            if ($externalId === '' || $name === '' || $code === '') {
                continue;
            }
            $status = ($row['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
            $active = ($row['active'] ?? true) !== false && $status !== 'inactive';
            $out[] = [
                'id' => $externalId,
                'name' => $name,
                'code' => strtoupper($code),
                'description' => trim((string) ($row['description'] ?? '')),
                'head' => isset($row['head']) && $row['head'] !== '' ? trim((string) $row['head']) : null,
                'status' => $status,
                'active' => $active,
                'autoApproveLowModerate' => ($row['autoApproveLowModerate'] ?? false) === true,
                'createdAt' => $row['createdAt'] ?? $fallbackNow,
                'updatedAt' => $row['updatedAt'] ?? $fallbackNow,
            ];
        }

        return $out;
    }

    /**
     * @param  mixed  $rows
     * @return list<array<string, mixed>>
     */
    private function normalizePositions(mixed $rows, string $fallbackNow): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $externalId = trim((string) ($row['id'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($externalId === '' || $name === '') {
                continue;
            }
            $out[] = [
                'id' => $externalId,
                'name' => $name,
                'active' => ($row['active'] ?? true) !== false,
                'createdAt' => $row['createdAt'] ?? $fallbackNow,
                'updatedAt' => $row['updatedAt'] ?? $fallbackNow,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    private function importDepartments(array $rows, bool $dryRun): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $externalId = (string) $row['id'];
            $attributes = [
                'name' => (string) $row['name'],
                'code' => (string) $row['code'],
                'description' => (string) ($row['description'] ?? ''),
                'head' => $row['head'] ?? null,
                'status' => (string) $row['status'],
                'active' => (bool) $row['active'],
                'auto_approve_low_moderate' => (bool) $row['autoApproveLowModerate'],
            ];

            if ($dryRun) {
                Department::query()->where('external_id', $externalId)->exists() ? $updated++ : $created++;
                continue;
            }

            $dept = Department::query()->where('external_id', $externalId)->first();
            if ($dept) {
                $dept->fill($attributes);
                $dept->save();
                $updated++;
            } else {
                Department::query()->create(array_merge($attributes, [
                    'external_id' => $externalId,
                ]));
                $created++;
            }
        }

        return compact('created', 'updated', 'skipped');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    private function importPositions(array $rows, bool $dryRun): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $externalId = (string) $row['id'];
            $attributes = [
                'name' => (string) $row['name'],
                'active' => (bool) $row['active'],
            ];

            if ($dryRun) {
                Position::query()->where('external_id', $externalId)->exists() ? $updated++ : $created++;
                continue;
            }

            $pos = Position::query()->where('external_id', $externalId)->first();
            if ($pos) {
                $pos->fill($attributes);
                $pos->save();
                $updated++;
            } else {
                Position::query()->create(array_merge($attributes, [
                    'external_id' => $externalId,
                ]));
                $created++;
            }
        }

        return compact('created', 'updated', 'skipped');
    }
}
