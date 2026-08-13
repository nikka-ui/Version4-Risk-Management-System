<?php

namespace App\Http\Controllers;

use App\Services\AdminDepartmentService;
use App\Services\ExpressOrgMirrorService;
use App\Support\Roles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 16 + Phase 7 slice 1: System Administrator department management (Blade GET + POST).
 */
class AdminDepartmentController extends Controller
{
    public function __construct(
        private readonly AdminDepartmentService $departments,
        private readonly ExpressOrgMirrorService $orgMirror,
    ) {}

    public function index(Request $request): View
    {
        $payload = $this->departments->list($request->query('action'));

        return view('admin.departments', $this->viewData($request, $payload));
    }

    public function edit(Request $request, string $id): View|RedirectResponse
    {
        $payload = $this->departments->list($request->query('action'), $id);

        if ($payload['editDept'] === null) {
            return redirect()->away('/admin/departments?flash=not_found');
        }

        return view('admin.departments', $this->viewData($request, $payload));
    }

    public function store(Request $request): RedirectResponse
    {
        $result = $this->departments->create($request->all());
        if (! empty($result['error'])) {
            return redirect()->away('/admin/departments?action=add&error='.rawurlencode((string) $result['error']));
        }

        $dept = $result['department'];
        $this->orgMirror->syncDepartment('upsert', $dept, $this->audit($request, 'department_created', 'Added department: '.$dept['name']), [
            'type' => 'department_added',
            'title' => 'Department added',
            'message' => $dept['name'],
        ]);

        return redirect()->away('/admin/departments?flash=created');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $result = $this->departments->update($id, $request->all());
        if (! empty($result['error'])) {
            $error = rawurlencode((string) $result['error']);
            if ($result['error'] === 'Department not found.') {
                return redirect()->away('/admin/departments?flash=not_found');
            }

            return redirect()->away('/admin/departments/'.rawurlencode($id).'/edit?error='.$error);
        }

        $dept = $result['department'];
        $this->orgMirror->syncDepartment('upsert', $dept, $this->audit($request, 'department_updated', 'Updated department: '.$dept['name']));

        return redirect()->away('/admin/departments?flash=updated');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $result = $this->departments->delete($id);
        if (! empty($result['error'])) {
            return redirect()->away('/admin/departments?error='.rawurlencode((string) $result['error']));
        }

        $dept = $result['department'];
        $this->orgMirror->syncDepartment('delete', $dept, $this->audit($request, 'department_deleted', 'Deleted department: '.$dept['name']));

        return redirect()->away('/admin/departments?flash=deleted');
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
            'activeNav' => 'departments',
            'title' => 'Department Management',
            'departments' => $payload['departments'],
            'editDept' => $payload['editDept'],
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
            'module' => 'Department Management',
            'description' => $description,
            'ip' => $request->ip(),
        ];
    }
}
