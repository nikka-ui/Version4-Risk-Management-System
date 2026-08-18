<?php

namespace App\Http\Controllers;

use App\Services\AdminSettingsService;
use App\Services\ExpressOrgMirrorService;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsApiController extends Controller
{
    public function __construct(
        private readonly AdminSettingsService $settings,
        private readonly ExpressOrgMirrorService $orgMirror,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json(['settings' => $this->settings->get()]);
    }

    public function update(Request $request): JsonResponse
    {
        $result = $this->settings->update($request->all());
        $this->orgMirror->syncSettings($result['settings'], $this->audit(
            $request,
            'settings_updated',
            'System settings were updated',
        ));

        return response()->json(['settings' => $result['settings']]);
    }

    public function resetLanding(Request $request): JsonResponse
    {
        $result = $this->settings->resetLanding();
        $this->orgMirror->syncSettings($result['settings'], $this->audit(
            $request,
            'settings_reset_landing',
            'Landing page text restored to system defaults',
        ));

        return response()->json(['settings' => $result['settings']]);
    }

    public function resetAi(Request $request): JsonResponse
    {
        $result = $this->settings->resetAi();
        $this->orgMirror->syncSettings($result['settings'], $this->audit(
            $request,
            'settings_reset_ai',
            'AI configuration restored to system defaults',
        ));

        return response()->json(['settings' => $result['settings']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function audit(Request $request, string $action, string $description): array
    {
        $user = $request->user();

        return [
            'username' => $user->username,
            'role' => $user->role,
            'roleLabel' => $user->role_label ?: Roles::label($user->role),
            'action' => $action,
            'module' => 'System Settings',
            'description' => $description,
            'ip' => $request->ip(),
        ];
    }
}
