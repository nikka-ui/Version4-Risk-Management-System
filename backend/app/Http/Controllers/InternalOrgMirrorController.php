<?php

namespace App\Http\Controllers;

use App\Services\StoreJsonOrgMirror;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 9 slice 6: Laravel /internal/org/* dual-write onto store.json.
 */
class InternalOrgMirrorController extends Controller
{
    public function __construct(private readonly StoreJsonOrgMirror $mirror) {}

    public function departments(Request $request): JsonResponse
    {
        $department = $request->input('department', []);
        $result = $this->mirror->applyDepartment(
            (string) $request->input('op', ''),
            is_array($department) ? $department : [],
            is_array($request->input('audit')) ? $request->input('audit') : null,
            is_array($request->input('notification')) ? $request->input('notification') : null,
        );

        return $this->okOrFail($result);
    }

    public function positions(Request $request): JsonResponse
    {
        $position = $request->input('position', []);
        $result = $this->mirror->applyPosition(
            (string) $request->input('op', ''),
            is_array($position) ? $position : [],
            is_array($request->input('audit')) ? $request->input('audit') : null,
        );

        return $this->okOrFail($result);
    }

    public function users(Request $request): JsonResponse
    {
        $user = $request->input('user', []);
        if (! is_array($user)) {
            $user = [];
        }
        if (! isset($user['username']) && $request->filled('username')) {
            $user['username'] = $request->input('username');
        }
        $result = $this->mirror->applyUser(
            (string) $request->input('op', ''),
            $user,
            is_array($request->input('audit')) ? $request->input('audit') : null,
            is_array($request->input('notification')) ? $request->input('notification') : null,
            is_array($request->input('credential')) ? $request->input('credential') : null,
        );

        return $this->okOrFail($result);
    }

    public function settings(Request $request): JsonResponse
    {
        $settings = $request->input('settings', []);
        $this->mirror->applySettings(
            is_array($settings) ? $settings : [],
            is_array($request->input('audit')) ? $request->input('audit') : null,
        );

        return response()->json(['status' => 'ok']);
    }

    /**
     * @param  array{error?: string}  $result
     */
    private function okOrFail(array $result): JsonResponse
    {
        if (! empty($result['error'])) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['status' => 'ok']);
    }
}
