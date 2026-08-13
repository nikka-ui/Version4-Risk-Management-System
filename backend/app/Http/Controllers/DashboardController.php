<?php

namespace App\Http\Controllers;

use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 6 slice 6: Employee stub dashboard + role-console redirect (Blade GET).
 */
class DashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $path = Roles::consolePath($user->role);
        if ($path !== '/dashboard') {
            return redirect()->away($path);
        }

        return view('dashboard', [
            'user' => $user->toIdentityArray(),
            'title' => 'Dashboard',
            'hint' => 'Access assigned risk workflows and departmental tasks.',
        ]);
    }
}
