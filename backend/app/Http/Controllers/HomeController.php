<?php

namespace App\Http\Controllers;

use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Phase 6 slice 1–2: edge `/` → login or role console (unprefixed when edge UI is on).
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        $edgeRoot = (bool) config('rms.edge_root', true);
        $edgeUi = (bool) config('rms.edge_ui', true);

        if (! $user) {
            if (! $edgeRoot) {
                return redirect()->away('/login');
            }

            return redirect()->away($edgeUi ? '/login' : '/laravel/login');
        }

        if (! $edgeRoot || $edgeUi) {
            return redirect()->away(Roles::consolePath($user->role));
        }

        return redirect()->away(Roles::bladeConsolePath($user->role));
    }
}
