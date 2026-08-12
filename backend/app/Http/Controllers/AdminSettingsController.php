<?php

namespace App\Http\Controllers;

use App\Services\AdminSettingsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 21: System Administrator settings (Blade GET).
 * Save/reset POSTs stay on Express.
 */
class AdminSettingsController extends Controller
{
    public function __construct(
        private readonly AdminSettingsService $settings,
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
}
