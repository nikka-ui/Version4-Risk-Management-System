<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Roles;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Idempotent import of Express store.json users into Postgres.
 * Does not modify store.json; Express remains source of truth for live auth.
 */
class ImportUsersFromStore extends Command
{
    protected $signature = 'rms:import-users
                            {--path= : Path to store.json (default: STORE_JSON_PATH / config)}
                            {--dry-run : Parse and report without writing}';

    protected $description = 'Import users from Express store.json into Laravel (idempotent upsert by username)';

    public function handle(): int
    {
        $path = $this->option('path') ?: config('rms.store_json_path');
        $path = (string) $path;

        if ($path === '' || ! is_readable($path)) {
            $this->error("store.json not readable at: {$path}");
            $this->line('Mount docker/web/data/store.json at /import/store.json, or pass --path.');
            $this->line('Example: php artisan rms:import-users --path=/import/store.json');

            return self::FAILURE;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            $this->error("Failed to read: {$path}");

            return self::FAILURE;
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($raw, true);
        if (! is_array($data) || ! isset($data['users']) || ! is_array($data['users'])) {
            $this->error('Invalid store.json: missing users array.');

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $dryRun = (bool) $this->option('dry-run');

        foreach ($data['users'] as $row) {
            if (! is_array($row)) {
                $skipped++;
                continue;
            }

            $username = strtolower(trim((string) ($row['username'] ?? '')));
            if ($username === '') {
                $skipped++;
                continue;
            }

            $plainPassword = (string) ($row['password'] ?? '');
            if ($plainPassword === '') {
                $this->warn("Skipping {$username}: empty password");
                $skipped++;
                continue;
            }

            $role = (string) ($row['role'] ?? Roles::EMPLOYEE);
            if (! Roles::isKnown($role)) {
                $this->warn("Unknown role '{$role}' for {$username} — importing anyway");
            }

            $active = ($row['active'] ?? true) !== false;
            $deleted = ($row['deleted'] ?? false) === true;
            $status = (string) ($row['status'] ?? ($deleted ? 'deleted' : ($active ? 'active' : 'inactive')));
            $displayName = (string) ($row['displayName'] ?? $username);
            $email = strtolower(trim((string) ($row['email'] ?? "{$username}@rms.local")));
            $roleLabel = (string) ($row['roleLabel'] ?? Roles::label($role));
            $employeeId = (string) ($row['employeeId'] ?? '');
            $department = (string) ($row['department'] ?? '');
            $position = (string) ($row['position'] ?? $roleLabel);
            $canManage = (bool) ($row['canManageUsers'] ?? ($role === Roles::ADMIN));
            $builtIn = (bool) ($row['builtIn'] ?? false);

            $attributes = [
                'name' => $displayName,
                'email' => $email,
                'role' => $role,
                'role_label' => $roleLabel,
                'employee_id' => $employeeId !== '' ? $employeeId : null,
                'department' => $department !== '' ? $department : null,
                'position' => $position !== '' ? $position : null,
                'can_manage_users' => $canManage,
                'built_in' => $builtIn,
                'active' => $active && ! $deleted,
                'status' => $status,
                'deleted' => $deleted,
                'deleted_at' => $deleted
                    ? $this->parseTimestamp($row['deletedAt'] ?? null) ?? now()
                    : null,
            ];

            if ($dryRun) {
                $exists = User::query()->where('username', $username)->exists();
                $this->line(($exists ? 'would update' : 'would create').": {$username} ({$role})");
                $exists ? $updated++ : $created++;
                continue;
            }

            $user = User::query()->where('username', $username)->first();
            if ($user) {
                // Avoid unique email collisions when another username holds this email.
                if ($user->email !== $email) {
                    $emailTaken = User::query()
                        ->where('email', $email)
                        ->where('id', '!=', $user->id)
                        ->exists();
                    if ($emailTaken) {
                        $attributes['email'] = $user->email;
                        $this->warn("Keeping existing email for {$username} (conflict on {$email})");
                    }
                }

                if (! Hash::check($plainPassword, $user->password)) {
                    $attributes['password'] = $plainPassword;
                }

                $user->fill($attributes);
                $user->save();
                $updated++;
            } else {
                $emailTaken = User::query()->where('email', $email)->exists();
                if ($emailTaken) {
                    $email = "{$username}@rms.imported.local";
                    $attributes['email'] = $email;
                    $this->warn("Email collision for {$username}; using {$email}");
                }

                User::query()->create(array_merge($attributes, [
                    'username' => $username,
                    'password' => $plainPassword,
                ]));
                $created++;
            }
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}Import complete: created={$created} updated={$updated} skipped={$skipped}");
        $this->line('Express store.json was not modified. Browser auth remains on Express.');

        return self::SUCCESS;
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
