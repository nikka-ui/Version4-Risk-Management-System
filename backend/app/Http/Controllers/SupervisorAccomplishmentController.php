<?php

namespace App\Http\Controllers;

use App\Services\SupervisorAccomplishmentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 5 slice 11: Ticket Reporter accomplishment history (Blade).
 */
class SupervisorAccomplishmentController extends Controller
{
    public function __construct(
        private readonly SupervisorAccomplishmentService $accomplishments,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('supervisor.accomplishments', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'accomplishments',
            'title' => 'Accomplishment reports',
            'accomplishments' => $this->accomplishments->listForUsername($user->username),
            'flash' => $request->query('flash'),
        ]);
    }
}
