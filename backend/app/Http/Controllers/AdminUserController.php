<?php

namespace App\Http\Controllers;

use App\Services\AdminUserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 15: System Administrator user management (Blade GET).
 * Create/edit/activate/deactivate/delete/reset-password POSTs stay on Express.
 */
class AdminUserController extends Controller
{
    public function __construct(
        private readonly AdminUserService $users,
    ) {}

    public function index(Request $request): View
    {
        return $this->render($request);
    }

    public function edit(Request $request, string $username): View|RedirectResponse
    {
        $payload = $this->users->list(
            $request->query('q'),
            $request->query('role'),
            $request->query('status'),
            $request->query('action'),
            $request->query('filter'),
            $username,
        );

        if ($payload['editUser'] === null) {
            return redirect('/laravel/admin/users?flash=not_found');
        }

        return view('admin.users', $this->viewData($request, $payload));
    }

    private function render(Request $request): View
    {
        $payload = $this->users->list(
            $request->query('q'),
            $request->query('role'),
            $request->query('status'),
            $request->query('action'),
            $request->query('filter'),
        );

        return view('admin.users', $this->viewData($request, $payload));
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
            'activeNav' => 'users',
            'title' => 'User Management',
            'users' => $payload['users'],
            'departments' => $payload['departments'],
            'roles' => $payload['roles'],
            'filters' => $payload['filters'],
            'editUser' => $payload['editUser'],
            'showForm' => $payload['showForm'],
            'flash' => $request->query('flash'),
            'error' => $request->query('error'),
        ];
    }
}
