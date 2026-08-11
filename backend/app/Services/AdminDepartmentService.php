<?php

namespace App\Services;

use App\Models\Department;

/**
 * Phase 5 slice 16: System Administrator department list from Laravel Postgres.
 */
class AdminDepartmentService
{
    /**
     * @return array{
     *   departments: list<array<string, mixed>>,
     *   editDept: array<string, mixed>|null,
     *   showForm: bool,
     *   action: string
     * }
     */
    public function list(?string $action = null, ?string $editExternalId = null): array
    {
        $action = trim((string) $action);
        $departments = Department::query()
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Department $d) => $d->toExpressArray())
            ->values()
            ->all();

        $editDept = null;
        if ($editExternalId !== null && $editExternalId !== '') {
            $record = Department::query()->where('external_id', $editExternalId)->first();
            $editDept = $record?->toExpressArray();
        }

        return [
            'departments' => $departments,
            'editDept' => $editDept,
            'showForm' => $action === 'add' || $editDept !== null,
            'action' => $action,
        ];
    }
}
