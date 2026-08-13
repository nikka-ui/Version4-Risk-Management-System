<?php

namespace App\Http\Controllers;

use App\Services\AdminSettingsService;
use App\Services\ExpressOrgMirrorService;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 21 + Phase 7 slice 4: System Administrator settings (Blade GET + POST).
 */
class AdminSettingsController extends Controller
{
    public function __construct(
        private readonly AdminSettingsService $settings,
        private readonly ExpressOrgMirrorService $orgMirror,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('admin.settings', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'settings',
            'title' => 'System Settings',
            'settings' => $this->settings->get(),
            'flash' => $request->query('flash'),
            'error' => $request->query('error'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $result = $this->settings->update($request->all());
        $this->orgMirror->syncSettings($result['settings'], $this->audit(
            $request,
            'settings_updated',
            'System settings were updated',
        ));

        return redirect()->away('/admin/settings?flash=settings_saved');
    }

    public function resetLanding(Request $request): RedirectResponse
    {
        $result = $this->settings->resetLanding();
        $this->orgMirror->syncSettings($result['settings'], $this->audit(
            $request,
            'settings_reset_landing',
            'Landing page text restored to system defaults',
        ));

        return redirect()->away('/admin/settings?flash=landing_reset');
    }

    public function resetAi(Request $request): RedirectResponse
    {
        $result = $this->settings->resetAi();
        $this->orgMirror->syncSettings($result['settings'], $this->audit(
            $request,
            'settings_reset_ai',
            'AI configuration restored to system defaults',
        ));

        return redirect()->away('/admin/settings?flash=ai_reset');
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
