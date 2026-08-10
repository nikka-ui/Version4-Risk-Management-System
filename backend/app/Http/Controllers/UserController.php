<?php

namespace App\Http\Controllers;

use App\Services\UserSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserSyncService $users,
    ) {}

    /**
     * Current authenticated user (Sanctum bearer token).
     */
    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return response()->json([
            'user' => $user->toIdentityArray(),
        ]);
    }

    /**
     * Phase 5 slice 2: upsert a user from Express admin dual-write (admin token).
     */
    public function sync(Request $request): JsonResponse
    {
        $user = $this->users->upsert($request->all());

        return response()->json([
            'user' => $user->toIdentityArray(),
        ]);
    }
}
