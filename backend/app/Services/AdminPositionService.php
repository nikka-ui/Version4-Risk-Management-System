<?php

namespace App\Services;

use App\Models\Position;

/**
 * Phase 5 slice 17 + Phase 7 slice 2: System Administrator positions (Blade list + mutations).
 */
class AdminPositionService
{
    /**
     * @return array{
     *   positions: list<array<string, mixed>>,
     *   editPos: array<string, mixed>|null,
     *   showForm: bool,
     *   action: string
     * }
     */
    public function list(?string $action = null, ?string $editExternalId = null): array
    {
        $action = trim((string) $action);
        $positions = Position::query()
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Position $p) => $p->toExpressArray())
            ->values()
            ->all();

        $editPos = null;
        if ($editExternalId !== null && $editExternalId !== '') {
            $record = Position::query()
                ->where('external_id', $editExternalId)
                ->where('active', true)
                ->first();
            $editPos = $record?->toExpressArray();
        }

        return [
            'positions' => $positions,
            'editPos' => $editPos,
            'showForm' => $action === 'add' || $editPos !== null,
            'action' => $action,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{position?: array<string, mixed>, error?: string}
     */
    public function create(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return ['error' => 'Position name is required.'];
        }

        $externalId = trim((string) ($input['id'] ?? ''));
        if ($externalId === '') {
            $externalId = 'pos-'.(int) round(microtime(true) * 1000);
        }

        $dup = Position::query()
            ->where('active', true)
            ->where('external_id', '!=', $externalId)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->exists();

        if ($dup) {
            return ['error' => 'Position already exists.'];
        }

        $pos = Position::query()->updateOrCreate(
            ['external_id' => $externalId],
            [
                'name' => $name,
                'active' => true,
            ],
        );

        return ['position' => $pos->fresh()->toExpressArray()];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{position?: array<string, mixed>, error?: string}
     */
    public function update(string $externalId, array $input): array
    {
        $pos = Position::query()
            ->where('external_id', $externalId)
            ->where('active', true)
            ->first();

        if (! $pos) {
            return ['error' => 'Position not found.'];
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return ['error' => 'Position name is required.'];
        }

        $dup = Position::query()
            ->where('active', true)
            ->where('external_id', '!=', $pos->external_id)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->exists();

        if ($dup) {
            return ['error' => 'Position already exists.'];
        }

        $pos->name = $name;
        $pos->save();

        return ['position' => $pos->fresh()->toExpressArray()];
    }

    /**
     * @return array{position?: array<string, mixed>, error?: string}
     */
    public function delete(string $externalId): array
    {
        $pos = Position::query()
            ->where('external_id', $externalId)
            ->where('active', true)
            ->first();

        if (! $pos) {
            return ['error' => 'Position not found.'];
        }

        $pos->active = false;
        $pos->save();

        return ['position' => $pos->toExpressArray()];
    }
}
