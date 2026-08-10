<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 5–6: authenticated Blade profile pages (admin + Ticket Reporter).
 */
class ProfileController extends Controller
{
    public function admin(Request $request): View
    {
        $user = $request->user();
        $identity = $user->toIdentityArray();

        return view('admin.profile', [
            'user' => $identity,
            'activeNav' => 'profile',
            'title' => 'Profile',
        ]);
    }

    public function supervisor(Request $request): View
    {
        $user = $request->user();
        $identity = $user->toIdentityArray();

        return view('supervisor.profile', [
            'user' => $identity,
            'activeNav' => 'profile',
            'title' => 'Profile',
        ]);
    }
}
