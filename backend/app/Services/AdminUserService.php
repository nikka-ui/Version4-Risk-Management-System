<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Collection;

/**
 * Phase 5 slice 15 + Phase 7 slice 3: System Administrator users (Blade list + mutations).
 */
class AdminUserService
{
    /**
     * @return array{
     *   users: list<array<string, mixed>>,
     *   departments: list<array{name: string, code: string}>,
     *   roles: list<array{id: string, label: string}>,
     *   filters: array{q: string, role: string, status: string, action: string, filter: string},
     *   editUser: array<string, mixed>|null,
     *   showForm: bool
     * }
     */
    public function list(?string $q = null, ?string $role = null, ?string $status = null, ?string $action = null, ?string $filter = null, ?string $editUsername = null): array
    {
        $filters = [
            'q' => trim((string) $q),
            'role' => trim((string) $role),
            'status' => trim((string) $status),
            'action' => trim((string) $action),
            'filter' => trim((string) $filter),
        ];

        $users = $this->filteredUsers($filters);

        $editUser = null;
        if ($editUsername !== null && $editUsername !== '') {
            $record = User::query()
                ->where('username', strtolower($editUsername))
                ->where('deleted', false)
                ->first();
            $editUser = $record?->toIdentityArray();
        }

        $showForm = $filters['action'] === 'add' || $editUser !== null;

        return [
            'users' => $users->map(fn (User $u) => $u->toIdentityArray())->values()->all(),
            'departments' => Department::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['name', 'code'])
                ->map(fn (Department $d) => ['name' => $d->name, 'code' => (string) $d->code])
                ->values()
                ->all(),
            'roles' => $this->assignableRoles(),
            'filters' => $filters,
            'editUser' => $editUser,
            'showForm' => $showForm,
        ];
    }

    /**
     * @param  array{q: string, role: string, status: string, filter: string}  $filters
     * @return Collection<int, User>
     */
    private function filteredUsers(array $filters): Collection
    {
        $query = User::query()->where('deleted', false)->orderBy('username');

        if ($filters['q'] !== '') {
            $q = '%'.mb_strtolower($filters['q']).'%';
            $query->where(function ($builder) use ($q) {
                $builder->whereRaw('LOWER(username) LIKE ?', [$q])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$q])
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$q])
                    ->orWhereRaw('LOWER(COALESCE(employee_id, \'\')) LIKE ?', [$q]);
            });
        }

        if ($filters['role'] !== '') {
            $query->where('role', $filters['role']);
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        } elseif ($filters['filter'] === 'active') {
            $query->where('status', 'active');
        }

        return $query->get();
    }

    /**
     * @return list<array{id: string, label: string}>
     */
    private function assignableRoles(): array
    {
        $roles = [];
        foreach (Roles::DEFINITIONS as $id => $def) {
            if (! ($def['assignable'] ?? false)) {
                continue;
            }
            $roles[] = [
                'id' => $id,
                'label' => $def['label'],
            ];
        }

        return $roles;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{user?: array<string, mixed>, password?: string, error?: string}
     */
    public function create(array $input): array
    {
        $username = strtolower(trim((string) ($input['username'] ?? '')));
        if (! preg_match('/^[a-z0-9][a-z0-9._-]{2,31}$/', $username)) {
            return ['error' => 'Username must be 3–32 characters (letters, numbers, . _ -).'];
        }

        $existing = User::query()->where('username', $username)->first();
        if ($existing && ! $existing->deleted && $existing->active) {
            return ['error' => 'Username already exists.'];
        }

        $password = (string) ($input['password'] ?? '');
        $confirm = $input['confirmPassword'] ?? $input['confirm_password'] ?? null;
        if (strlen($password) < 6) {
            return ['error' => 'Password must be at least 6 characters.'];
        }
        if ($confirm !== null && $password !== (string) $confirm) {
            return ['error' => 'Passwords do not match.'];
        }

        $role = trim((string) ($input['role'] ?? ''));
        if (! Roles::isAssignable($role)) {
            return ['error' => 'Invalid role selected.'];
        }

        $displayName = trim((string) ($input['displayName'] ?? $input['name'] ?? $username)) ?: $username;
        $email = strtolower(trim((string) ($input['email'] ?? ''))) ?: $username.'@rms.local';
        $employeeId = trim((string) ($input['employeeId'] ?? $input['employee_id'] ?? '')) ?: $this->nextEmployeeId();
        $department = trim((string) ($input['department'] ?? 'Administration')) ?: 'Administration';
        $position = trim((string) ($input['position'] ?? Roles::label($role))) ?: Roles::label($role);

        $attributes = [
            'name' => $displayName,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'role_label' => Roles::label($role),
            'employee_id' => $employeeId,
            'department' => $department,
            'position' => $position,
            'can_manage_users' => $role === Roles::ADMIN,
            'built_in' => false,
            'active' => true,
            'status' => 'active',
            'deleted' => false,
            'deleted_at' => null,
        ];

        if ($existing) {
            $existing->fill($attributes);
            $existing->save();
            $user = $existing->fresh();
        } else {
            $attributes['username'] = $username;
            $user = User::query()->create($attributes)->fresh();
        }

        return ['user' => $user->toIdentityArray(), 'password' => $password];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{user?: array<string, mixed>, error?: string}
     */
    public function update(string $username, array $input): array
    {
        $user = $this->findActiveUsername($username);
        if (! $user) {
            return ['error' => 'User not found.'];
        }

        if (array_key_exists('displayName', $input) || array_key_exists('name', $input)) {
            $name = trim((string) ($input['displayName'] ?? $input['name'] ?? ''));
            if ($name !== '') {
                $user->name = $name;
            }
        }
        if (array_key_exists('email', $input)) {
            $email = strtolower(trim((string) $input['email']));
            if ($email !== '') {
                $user->email = $email;
            }
        }
        if (array_key_exists('employeeId', $input) || array_key_exists('employee_id', $input)) {
            $employeeId = trim((string) ($input['employeeId'] ?? $input['employee_id'] ?? ''));
            $user->employee_id = $employeeId !== '' ? $employeeId : $user->employee_id;
        }
        if (array_key_exists('department', $input)) {
            $user->department = trim((string) $input['department']) ?: $user->department;
        }
        if (array_key_exists('position', $input)) {
            $user->position = trim((string) $input['position']) ?: $user->position;
        }
        if (array_key_exists('role', $input) && Roles::isAssignable((string) $input['role'])) {
            if (($user->built_in || $user->username === 'admin') && $input['role'] !== Roles::ADMIN) {
                return ['error' => 'Cannot change role of the primary admin account.'];
            }
            $user->role = (string) $input['role'];
            $user->role_label = Roles::label($user->role);
            $user->can_manage_users = $user->role === Roles::ADMIN;
        }

        $status = trim((string) ($input['status'] ?? ''));
        if ($status !== '' && $user->username !== 'admin') {
            $active = $status === 'active';
            $user->active = $active;
            $user->status = $active ? 'active' : 'inactive';
        }

        $user->save();

        return ['user' => $user->fresh()->toIdentityArray()];
    }

    /**
     * @return array{user?: array<string, mixed>, error?: string}
     */
    public function setStatus(string $username, bool $active): array
    {
        $user = $this->findActiveUsername($username);
        if (! $user) {
            return ['error' => 'User not found.'];
        }
        if (($user->built_in || $user->username === 'admin') && ! $active) {
            return ['error' => 'The primary administrator account cannot be deactivated.'];
        }

        $user->active = $active;
        $user->status = $active ? 'active' : 'inactive';
        $user->save();

        return ['user' => $user->fresh()->toIdentityArray()];
    }

    /**
     * @return array{user?: array<string, mixed>, error?: string}
     */
    public function delete(string $username): array
    {
        $user = $this->findActiveUsername($username);
        if (! $user) {
            return ['error' => 'User not found.'];
        }
        if ($user->built_in) {
            return ['error' => 'Built-in accounts cannot be deleted.'];
        }
        if ($user->username === 'admin') {
            return ['error' => 'The administrator account cannot be deleted.'];
        }

        $user->deleted = true;
        $user->deleted_at = now();
        $user->active = false;
        $user->status = 'deleted';
        $user->save();

        return ['user' => $user->fresh()->toIdentityArray()];
    }

    /**
     * @return array{user?: array<string, mixed>, password?: string, error?: string}
     */
    public function resetPassword(string $username, array $input): array
    {
        $user = $this->findActiveUsername($username);
        if (! $user) {
            return ['error' => 'User not found.'];
        }

        $password = (string) ($input['password'] ?? '');
        $confirm = $input['confirmPassword'] ?? $input['confirm_password'] ?? null;
        if (strlen($password) < 6) {
            return ['error' => 'Password must be at least 6 characters.'];
        }
        if ($confirm !== null && $password !== (string) $confirm) {
            return ['error' => 'Passwords do not match.'];
        }

        $user->password = $password;
        $user->save();

        return ['user' => $user->fresh()->toIdentityArray(), 'password' => $password];
    }

    public function findIdentity(string $username): ?array
    {
        $user = $this->findActiveUsername($username);

        return $user?->toIdentityArray();
    }

    private function findActiveUsername(string $username): ?User
    {
        return User::query()
            ->where('username', strtolower(trim($username)))
            ->where('deleted', false)
            ->first();
    }

    private function nextEmployeeId(): string
    {
        $max = 0;
        foreach (User::query()->whereNotNull('employee_id')->pluck('employee_id') as $id) {
            if (preg_match('/^EMP-(\d+)$/i', (string) $id, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        return 'EMP-'.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }
}
