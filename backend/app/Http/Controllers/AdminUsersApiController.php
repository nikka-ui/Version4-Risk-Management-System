<?php

namespace App\Http\Controllers;

use App\Services\AdminUserService;
use App\Services\ExpressOrgMirrorService;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUsersApiController extends Controller
{
    public function __construct(
        private readonly AdminUserService $users,
        private readonly ExpressOrgMirrorService $orgMirror,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $payload = $this->users->list(
            $request->query('q'),
            $request->query('role'),
            $request->query('status'),
            null,
            $request->query('filter'),
        );

        return response()->json([
            'users' => $payload['users'],
            'departments' => $payload['departments'],
            'roles' => $payload['roles'],
        ]);
    }

    public function show(string $username): JsonResponse
    {
        $user = $this->users->findIdentity($username);
        if ($user === null) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        return response()->json(['user' => $user]);
    }

    public function store(Request $request): JsonResponse
    {
        $result = $this->users->create($request->all());
        if (! empty($result['error'])) {
            return $this->error($result['error']);
        }

        $user = $result['user'];
        $this->orgMirror->syncUser('upsert', $this->mirrorPayload($user, $result['password'] ?? null), $this->audit(
            $request,
            'user_created',
            'Created account: '.$user['displayName'].' ('.$user['username'].')',
            $user['username'],
        ));

        return response()->json(['user' => $user], 201);
    }

    public function update(Request $request, string $username): JsonResponse
    {
        $result = $this->users->update($username, $request->all());
        if (! empty($result['error'])) {
            return $this->error($result['error']);
        }

        $user = $result['user'];
        $this->orgMirror->syncUser('upsert', $this->mirrorPayload($user), $this->audit(
            $request,
            'user_updated',
            'Updated account: '.$user['displayName'],
            $user['username'],
        ));

        return response()->json(['user' => $user]);
    }

    public function destroy(Request $request, string $username): JsonResponse
    {
        $result = $this->users->delete($username);
        if (! empty($result['error'])) {
            return $this->error($result['error']);
        }

        $user = $result['user'];
        $this->orgMirror->syncUser('delete', ['username' => $user['username']], $this->audit(
            $request,
            'user_deleted',
            'Deleted account: '.$user['displayName'],
            $user['username'],
        ));

        return response()->json(['user' => $user]);
    }

    public function activate(Request $request, string $username): JsonResponse
    {
        return $this->toggle($request, $username, true);
    }

    public function deactivate(Request $request, string $username): JsonResponse
    {
        return $this->toggle($request, $username, false);
    }

    public function resetPassword(Request $request, string $username): JsonResponse
    {
        $result = $this->users->resetPassword($username, $request->all());
        if (! empty($result['error'])) {
            return $this->error($result['error']);
        }

        $user = $result['user'];
        $this->orgMirror->syncUser('upsert', $this->mirrorPayload($user, $result['password'] ?? null), $this->audit(
            $request,
            'password_reset',
            'Reset password for: '.$user['displayName'],
            $user['username'],
        ));

        return response()->json(['user' => $user]);
    }

    private function toggle(Request $request, string $username, bool $active): JsonResponse
    {
        $result = $this->users->setStatus($username, $active);
        if (! empty($result['error'])) {
            return $this->error($result['error']);
        }

        $user = $result['user'];
        $this->orgMirror->syncUser('upsert', $this->mirrorPayload($user), $this->audit(
            $request,
            $active ? 'user_activated' : 'user_deactivated',
            ($active ? 'Activated' : 'Deactivated').' account: '.$user['displayName'],
            $user['username'],
        ));

        return response()->json(['user' => $user]);
    }

    private function error(string $message): JsonResponse
    {
        $status = $message === 'User not found.' ? 404 : 422;

        return response()->json(['message' => $message], $status);
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array<string, mixed>
     */
    private function mirrorPayload(array $user, ?string $password = null): array
    {
        $payload = [
            'username' => $user['username'],
            'displayName' => $user['displayName'],
            'email' => $user['email'],
            'role' => $user['role'],
            'roleLabel' => $user['roleLabel'],
            'employeeId' => $user['employeeId'],
            'department' => $user['department'],
            'position' => $user['position'],
            'canManageUsers' => (bool) ($user['canManageUsers'] ?? false),
            'builtIn' => (bool) ($user['builtIn'] ?? false),
            'active' => (bool) ($user['active'] ?? true),
            'status' => $user['status'] ?? 'active',
            'deleted' => ($user['status'] ?? '') === 'deleted',
        ];
        if ($password !== null && $password !== '') {
            $payload['password'] = $password;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function audit(Request $request, string $action, string $description, ?string $targetUser = null): array
    {
        $user = $request->user();

        return [
            'username' => $user->username,
            'role' => $user->role,
            'roleLabel' => $user->role_label ?: Roles::label($user->role),
            'action' => $action,
            'module' => 'User Management',
            'description' => $description,
            'targetUser' => $targetUser,
            'ip' => $request->ip(),
        ];
    }
}
