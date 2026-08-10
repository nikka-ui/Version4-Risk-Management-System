<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SupervisorTicketFormService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 5 slice 13: smoke Ticket Reporter create/edit/preview Blade forms.
 */
class SmokeSlice5ReporterTicketForm extends Command
{
    protected $signature = 'rms:smoke-slice5-reporter-ticket-form';

    protected $description = 'Smoke Laravel Ticket Reporter ticket form Blade pages';

    public function handle(SupervisorTicketFormService $forms): int
    {
        $username = 'smoke_frm_'.bin2hex(random_bytes(3));
        $password = 'SmokeFrm1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Form Reporter',
            'email' => "{$username}@rms.local",
            'password' => $password,
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
            'department' => 'Operations',
            'position' => 'Risk Reporter',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);
        $this->info("created {$username}");

        Auth::login($user);
        $newForm = $forms->newForm($user);
        $newHtml = view('supervisor.ticket-form', array_merge($newForm, [
            'user' => $user->toIdentityArray(),
            'title' => 'New report',
            'error' => null,
            'flash' => null,
        ]))->render();

        if (! str_contains($newHtml, 'NEW RISK REPORT') || ! str_contains($newHtml, '/supervisor/tickets/new/preview')) {
            Auth::logout();
            $user->delete();
            $this->error('new ticket form Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('new ticket form Blade OK');

        Auth::logout();
        $user->delete();
        $this->info('cleaned up');
        $this->line('Flag USE_LARAVEL_REPORTER_TICKET_FORM_UI: Express form GETs → Blade; POST stays Express');

        return self::SUCCESS;
    }
}
