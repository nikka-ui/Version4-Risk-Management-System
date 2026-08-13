<?php

namespace App\Services;

use App\Models\Department;

/**
 * Phase 5 slice 16 + Phase 7 slice 1: System Administrator departments (Blade list + mutations).
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

    /**
     * @param  array<string, mixed>  $input
     * @return array{department?: array<string, mixed>, error?: string}
     */
    public function create(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        if ($name === '' || $code === '') {
            return ['error' => 'Department name and code are required.'];
        }

        $status = (($input['status'] ?? 'active') === 'inactive') ? 'inactive' : 'active';
        $externalId = trim((string) ($input['id'] ?? ''));
        if ($externalId === '') {
            $externalId = 'dept-'.(int) round(microtime(true) * 1000);
        }

        $dup = Department::query()
            ->where('active', true)
            ->where('external_id', '!=', $externalId)
            ->where(function ($q) use ($code, $name) {
                $q->where('code', $code)
                    ->orWhereRaw('LOWER(name) = ?', [strtolower($name)]);
            })
            ->exists();

        if ($dup) {
            return ['error' => 'A department with that name or code already exists.'];
        }

        $dept = Department::query()->updateOrCreate(
            ['external_id' => $externalId],
            [
                'name' => $name,
                'code' => $code,
                'description' => trim((string) ($input['description'] ?? '')),
                'head' => $this->nullableHead($input['head'] ?? null),
                'status' => $status,
                'active' => $status !== 'inactive',
                'auto_approve_low_moderate' => (bool) ($input['autoApproveLowModerate'] ?? false),
            ],
        );

        return ['department' => $dept->fresh()->toExpressArray()];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{department?: array<string, mixed>, error?: string}
     */
    public function update(string $externalId, array $input): array
    {
        $dept = Department::query()->where('external_id', $externalId)->first();
        if (! $dept || ! $dept->active) {
            return ['error' => 'Department not found.'];
        }

        if (array_key_exists('name', $input)) {
            $name = trim((string) $input['name']);
            if ($name === '') {
                return ['error' => 'Department name and code are required.'];
            }
            $dept->name = $name;
        }
        if (array_key_exists('code', $input)) {
            $code = strtoupper(trim((string) $input['code']));
            if ($code === '') {
                return ['error' => 'Department name and code are required.'];
            }
            $dept->code = $code;
        }
        if (array_key_exists('description', $input)) {
            $dept->description = trim((string) $input['description']);
        }
        if (array_key_exists('head', $input)) {
            $dept->head = $this->nullableHead($input['head']);
        }
        if (array_key_exists('status', $input)) {
            $dept->status = ($input['status'] === 'inactive') ? 'inactive' : 'active';
            $dept->active = $dept->status !== 'inactive';
        }
        if (array_key_exists('autoApproveLowModerate', $input)) {
            $dept->auto_approve_low_moderate = (bool) $input['autoApproveLowModerate'];
        }

        $dup = Department::query()
            ->where('active', true)
            ->where('external_id', '!=', $dept->external_id)
            ->where(function ($q) use ($dept) {
                $q->where('code', $dept->code)
                    ->orWhereRaw('LOWER(name) = ?', [strtolower($dept->name)]);
            })
            ->exists();

        if ($dup) {
            return ['error' => 'A department with that name or code already exists.'];
        }

        $dept->save();

        return ['department' => $dept->fresh()->toExpressArray()];
    }

    /**
     * @return array{department?: array<string, mixed>, error?: string}
     */
    public function delete(string $externalId): array
    {
        $dept = Department::query()
            ->where('external_id', $externalId)
            ->where('active', true)
            ->first();

        if (! $dept) {
            return ['error' => 'Department not found.'];
        }

        $dept->active = false;
        $dept->status = 'inactive';
        $dept->save();

        return ['department' => $dept->toExpressArray()];
    }

    private function nullableHead(mixed $head): ?string
    {
        $value = trim((string) ($head ?? ''));

        return $value === '' ? null : $value;
    }
}
