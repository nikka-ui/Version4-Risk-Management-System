<?php

namespace App\Services;

use App\Models\Position;

/**
 * Phase 5 slice 17: System Administrator position list from Laravel Postgres.
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
}
