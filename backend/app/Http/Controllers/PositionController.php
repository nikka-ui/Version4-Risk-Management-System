<?php

namespace App\Http\Controllers;

use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    public function index(): JsonResponse
    {
        $positions = Position::query()
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Position $pos) => $pos->toExpressArray())
            ->values();

        return response()->json(['positions' => $positions]);
    }

    public function show(string $externalId): JsonResponse
    {
        $pos = Position::query()
            ->where('external_id', $externalId)
            ->where('active', true)
            ->first();

        if (! $pos) {
            return response()->json(['message' => 'Position not found.'], 404);
        }

        return response()->json(['position' => $pos->toExpressArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'name' => ['required', 'string', 'max:128'],
        ]);

        $name = trim($fields['name']);
        if ($name === '') {
            return response()->json(['message' => 'Position name is required.'], 422);
        }

        $dup = Position::query()
            ->where('active', true)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->exists();

        if ($dup) {
            return response()->json(['message' => 'Position already exists.'], 422);
        }

        $pos = Position::query()->create([
            'external_id' => 'pos-'.(int) round(microtime(true) * 1000),
            'name' => $name,
            'active' => true,
        ]);

        return response()->json(['position' => $pos->toExpressArray()], 201);
    }

    public function update(Request $request, string $externalId): JsonResponse
    {
        $pos = Position::query()
            ->where('external_id', $externalId)
            ->where('active', true)
            ->first();

        if (! $pos) {
            return response()->json(['message' => 'Position not found.'], 404);
        }

        $fields = $request->validate([
            'name' => ['required', 'string', 'max:128'],
        ]);

        $name = trim($fields['name']);
        if ($name === '') {
            return response()->json(['message' => 'Position name is required.'], 422);
        }

        $pos->name = $name;
        $pos->save();

        return response()->json(['position' => $pos->toExpressArray()]);
    }

    public function destroy(string $externalId): JsonResponse
    {
        $pos = Position::query()
            ->where('external_id', $externalId)
            ->where('active', true)
            ->first();

        if (! $pos) {
            return response()->json(['message' => 'Position not found.'], 404);
        }

        $pos->active = false;
        $pos->save();

        return response()->json(['position' => $pos->toExpressArray()]);
    }
}
