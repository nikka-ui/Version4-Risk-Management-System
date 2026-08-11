<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Collection;

/**
 * Phase 5 slice 15: System Administrator user list/filter data from Laravel Postgres.
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
}
