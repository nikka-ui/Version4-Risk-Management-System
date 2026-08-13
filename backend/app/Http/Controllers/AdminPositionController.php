<?php

namespace App\Http\Controllers;

use App\Services\AdminPositionService;
use App\Services\ExpressOrgMirrorService;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 17 + Phase 7 slice 2: System Administrator position management (Blade GET + POST).
 */
class AdminPositionController extends Controller
{
    public function __construct(
        private readonly AdminPositionService $positions,
        private readonly ExpressOrgMirrorService $orgMirror,
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
            return redirect()->away('/admin/positions?flash=not_found');
        }

        return view('admin.positions', $this->viewData($request, $payload));
    }

    public function store(Request $request): RedirectResponse
    {
        $result = $this->positions->create($request->all());
        if (! empty($result['error'])) {
            return redirect()->away('/admin/positions?action=add&error='.rawurlencode((string) $result['error']));
        }

        $pos = $result['position'];
        $this->orgMirror->syncPosition('upsert', $pos, $this->audit($request, 'position_created', 'Added position: '.$pos['name']));

        return redirect()->away('/admin/positions?flash=created');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $result = $this->positions->update($id, $request->all());
        if (! empty($result['error'])) {
            if ($result['error'] === 'Position not found.') {
                return redirect()->away('/admin/positions?flash=not_found');
            }

            return redirect()->away('/admin/positions/'.rawurlencode($id).'/edit?error='.rawurlencode((string) $result['error']));
        }

        $pos = $result['position'];
        $this->orgMirror->syncPosition('upsert', $pos, $this->audit($request, 'position_updated', 'Updated position: '.$pos['name']));

        return redirect()->away('/admin/positions?flash=updated');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $result = $this->positions->delete($id);
        if (! empty($result['error'])) {
            return redirect()->away('/admin/positions?error='.rawurlencode((string) $result['error']));
        }

        $pos = $result['position'];
        $this->orgMirror->syncPosition('delete', $pos, $this->audit($request, 'position_deleted', 'Deleted position: '.$pos['name']));

        return redirect()->away('/admin/positions?flash=deleted');
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
            'module' => 'Position Management',
            'description' => $description,
            'ip' => $request->ip(),
        ];
    }
}
