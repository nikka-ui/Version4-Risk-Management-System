<?php

namespace App\Services;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Phase 5 slice 2: upsert Express store users into Laravel when USE_LARAVEL_AUTH is on.
 */
class UserSyncService
{
    public function upsert(array $input): User
    {
        $username = strtolower(trim((string) ($input['username'] ?? '')));
        if ($username === '') {
            throw ValidationException::withMessages([
                'username' => ['Username is required.'],
            ]);
        }

        $user = User::query()->where('username', $username)->first();
        $role = trim((string) ($input['role'] ?? ($user?->role ?? Roles::SUPERVISOR)));
        $roleLabel = trim((string) ($input['roleLabel'] ?? $input['role_label'] ?? ''))
            ?: Roles::label($role);

        $attributes = [
            'name' => trim((string) ($input['displayName'] ?? $input['name'] ?? $username)) ?: $username,
            'email' => trim((string) ($input['email'] ?? "{$username}@rms.local")) ?: "{$username}@rms.local",
            'role' => $role,
            'role_label' => $roleLabel,
            'employee_id' => trim((string) ($input['employeeId'] ?? $input['employee_id'] ?? '')) ?: null,
            'department' => trim((string) ($input['department'] ?? '')) ?: null,
            'position' => trim((string) ($input['position'] ?? '')) ?: null,
            'can_manage_users' => (bool) ($input['canManageUsers'] ?? $input['can_manage_users'] ?? ($role === Roles::ADMIN)),
            'built_in' => (bool) ($input['builtIn'] ?? $input['built_in'] ?? false),
            'deleted' => (bool) ($input['deleted'] ?? false),
        ];

        if (array_key_exists('active', $input) || array_key_exists('status', $input)) {
            $active = array_key_exists('active', $input)
                ? (bool) $input['active']
                : (($input['status'] ?? '') !== 'inactive');
            $attributes['active'] = $active;
            $attributes['status'] = trim((string) ($input['status'] ?? ($active ? 'active' : 'inactive')));
        }

        $password = (string) ($input['password'] ?? '');
        if ($password !== '') {
            $attributes['password'] = $password;
        } elseif (! $user) {
            throw ValidationException::withMessages([
                'password' => ['Password is required when creating a user.'],
            ]);
        }

        if ($user) {
            if ($password !== '' && Hash::check($password, $user->password)) {
                unset($attributes['password']);
            }
            $user->fill($attributes);
            $user->save();

            return $user->fresh();
        }

        $attributes['username'] = $username;
        $attributes['active'] = $attributes['active'] ?? true;
        $attributes['status'] = $attributes['status'] ?? 'active';
        $attributes['deleted'] = false;

        return User::query()->create($attributes)->fresh();
    }
}
