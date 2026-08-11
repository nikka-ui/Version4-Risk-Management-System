<?php

namespace App\Http\Controllers;

use App\Services\AdminDepartmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 16: System Administrator department management (Blade GET).
 * Create/edit/delete POSTs stay on Express.
 */
class AdminDepartmentController extends Controller
{
    public function __construct(
        private readonly AdminDepartmentService $departments,
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
            return redirect('/laravel/admin/departments?flash=not_found');
        }

        return view('admin.departments', $this->viewData($request, $payload));
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
}
