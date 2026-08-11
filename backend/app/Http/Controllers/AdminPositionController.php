<?php

namespace App\Http\Controllers;

use App\Services\AdminPositionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 17: System Administrator position management (Blade GET).
 * Create/edit/delete POSTs stay on Express.
 */
class AdminPositionController extends Controller
{
    public function __construct(
        private readonly AdminPositionService $positions,
    ) {}

    public function index(Request $request): View
    {
        $payload = $this->positions->list($request->query('action'));

        return view('admin.positions', $this->viewData($request, $payload));
    }

    public function edit(Request $request, string $id): View|RedirectResponse
    {
        $payload = $this->positions->list($request->query('action'), $id);

        if ($payload['editPos'] === null) {
            return redirect('/laravel/admin/positions?flash=not_found');
        }

        return view('admin.positions', $this->viewData($request, $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function viewData(Request $request, array $payload): array
    {
        $user = $request->user();

        return [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'positions',
            'title' => 'Position Management',
            'positions' => $payload['positions'],
            'editPos' => $payload['editPos'],
            'showForm' => $payload['showForm'],
            'flash' => $request->query('flash'),
            'error' => $request->query('error'),
        ];
    }
}
