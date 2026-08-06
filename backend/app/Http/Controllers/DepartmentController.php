<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        $departments = Department::query()
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Department $dept) => $dept->toExpressArray())
            ->values();

        return response()->json(['departments' => $departments]);
    }

    public function show(string $externalId): JsonResponse
    {
        $dept = Department::query()
            ->where('external_id', $externalId)
            ->where('active', true)
            ->first();

        if (! $dept) {
            return response()->json(['message' => 'Department not found.'], 404);
        }

        return response()->json(['department' => $dept->toExpressArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'code' => ['required', 'string', 'max:32'],
            'description' => ['sometimes', 'string'],
            'head' => ['nullable', 'string', 'max:128'],
            'status' => ['sometimes', 'in:active,inactive'],
            'autoApproveLowModerate' => ['sometimes', 'boolean'],
        ]);

        $name = trim($fields['name']);
        $code = strtoupper(trim($fields['code']));
        $status = ($fields['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        $dup = Department::query()
            ->where('active', true)
            ->where(function ($q) use ($code, $name) {
                $q->where('code', $code)
                    ->orWhereRaw('LOWER(name) = ?', [strtolower($name)]);
            })
            ->exists();

        if ($dup) {
            return response()->json(['message' => 'A department with that name or code already exists.'], 422);
        }

        $dept = Department::query()->create([
            'external_id' => 'dept-'.(int) round(microtime(true) * 1000),
            'name' => $name,
            'code' => $code,
            'description' => trim((string) ($fields['description'] ?? '')),
            'head' => isset($fields['head']) && $fields['head'] !== '' ? trim((string) $fields['head']) : null,
            'status' => $status,
            'active' => $status !== 'inactive',
            'auto_approve_low_moderate' => (bool) ($fields['autoApproveLowModerate'] ?? false),
        ]);

        return response()->json(['department' => $dept->toExpressArray()], 201);
    }

    public function update(Request $request, string $externalId): JsonResponse
    {
        $dept = Department::query()->where('external_id', $externalId)->first();
        if (! $dept || ! $dept->active) {
            return response()->json(['message' => 'Department not found.'], 404);
        }

        $fields = $request->validate([
            'name' => ['sometimes', 'string', 'max:128'],
            'code' => ['sometimes', 'string', 'max:32'],
            'description' => ['sometimes', 'string'],
            'head' => ['nullable', 'string', 'max:128'],
            'status' => ['sometimes', 'in:active,inactive'],
            'autoApproveLowModerate' => ['sometimes', 'boolean'],
        ]);

        if (isset($fields['name'])) {
            $dept->name = trim($fields['name']);
        }
        if (isset($fields['code'])) {
            $dept->code = strtoupper(trim($fields['code']));
        }
        if (array_key_exists('description', $fields)) {
            $dept->description = trim((string) $fields['description']);
        }
        if (array_key_exists('head', $fields)) {
            $dept->head = $fields['head'] !== null && $fields['head'] !== ''
                ? trim((string) $fields['head'])
                : null;
        }
        if (isset($fields['status'])) {
            $dept->status = $fields['status'] === 'inactive' ? 'inactive' : 'active';
            $dept->active = $dept->status !== 'inactive';
        }
        if (array_key_exists('autoApproveLowModerate', $fields)) {
            $dept->auto_approve_low_moderate = (bool) $fields['autoApproveLowModerate'];
        }

        $dept->save();

        return response()->json(['department' => $dept->fresh()->toExpressArray()]);
    }

    public function destroy(string $externalId): JsonResponse
    {
        $dept = Department::query()
            ->where('external_id', $externalId)
            ->where('active', true)
            ->first();

        if (! $dept) {
            return response()->json(['message' => 'Department not found.'], 404);
        }

        $dept->active = false;
        $dept->status = 'inactive';
        $dept->save();

        return response()->json(['department' => $dept->toExpressArray()]);
    }
}
