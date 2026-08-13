<?php

namespace App\Console\Commands;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\ExecutiveDashboardService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 6 slice 3: smoke Executive oversight Blade pages.
 */
class SmokeSlice6ExecutivePages extends Command
{
    protected $signature = 'rms:smoke-slice6-executive-pages';

    protected $description = 'Smoke Laravel Executive heatmap/reports/trends/statistics/departments/register Blade pages';

    public function handle(ExecutiveDashboardService $dashboard): int
    {
        $username = 'smoke_exp_'.bin2hex(random_bytes(3));
        $password = 'SmokeExp1!';

        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke Executive Pages',
            'email' => "{$username}@rms.local",
            'password' => $password,
            'role' => Roles::EXECUTIVE,
            'role_label' => Roles::label(Roles::EXECUTIVE),
            'department' => 'IT',
            'position' => 'Executive Committee',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        $ticket = RiskTicket::query()->create([
            'external_id' => 'ext-'.$username,
            'reference' => 'RISK-SMOKE-'.strtoupper(substr($username, -6)),
            'title' => 'Smoke executive pages ticket',
            'description' => 'Executive pages smoke detail',
            'status' => 'assigned',
            'department' => 'IT',
            'category' => 'operational',
            'likelihood' => 4,
            'impact' => 5,
            'submitted_by' => 'reporter_smoke',
            'submitted_by_name' => 'Smoke Reporter',
            'ownership' => ['state' => 'accepted'],
            'ai' => ['severity' => 5],
            'evidence_count' => 0,
            'deleted' => false,
            'source_updated_at' => now(),
            'submitted_at' => now(),
        ]);
        $this->info("created {$username} + ticket");

        Auth::login($user);
        $payload = $dashboard->data();
        $identity = $user->toIdentityArray();

        $checks = [
            [
                'name' => 'heatmap',
                'view' => 'executive.heatmap',
                'data' => [
                    'user' => $identity,
                    'activeNav' => 'heatmap',
                    'title' => 'Heatmap',
                    'stats' => $payload['stats'],
                    'matrix' => $payload['matrix'],
                    'flash' => null,
                ],
                'needles' => ['Organization risk matrix', 'href="/executive/heatmap"'],
            ],
            [
                'name' => 'reports',
                'view' => 'executive.reports',
                'data' => [
                    'user' => $identity,
                    'activeNav' => 'reports',
                    'title' => 'Reports',
                    'stats' => $payload['stats'],
                    'categories' => $payload['categories'],
                    'highCriticalTickets' => array_slice($payload['highCriticalTickets'], 0, 15),
                    'flash' => null,
                ],
                'needles' => ['By risk level', 'Recent High', $ticket->reference],
            ],
            [
                'name' => 'trends',
                'view' => 'executive.trends',
                'data' => [
                    'user' => $identity,
                    'activeNav' => 'trends',
                    'title' => 'Trends',
                    'stats' => $payload['stats'],
                    'trends' => $payload['trends'],
                    'flash' => null,
                ],
                'needles' => ['Report volume trend'],
            ],
            [
                'name' => 'statistics',
                'view' => 'executive.statistics',
                'data' => [
                    'user' => $identity,
                    'activeNav' => 'statistics',
                    'title' => 'Statistics',
                    'stats' => $payload['stats'],
                    'byStatus' => $payload['byStatus'],
                    'flash' => null,
                ],
                'needles' => ['By workflow status', 'Assigned'],
            ],
            [
                'name' => 'departments',
                'view' => 'executive.departments',
                'data' => [
                    'user' => $identity,
                    'activeNav' => 'departments',
                    'title' => 'Department Performance',
                    'stats' => $payload['stats'],
                    'departments' => $payload['departments'],
                    'flash' => null,
                ],
                'needles' => ['Department Performance', 'IT'],
            ],
        ];

        foreach ($checks as $check) {
            $html = view($check['view'], $check['data'])->render();
            foreach ($check['needles'] as $needle) {
                if (! str_contains($html, $needle)) {
                    Auth::logout();
                    $ticket->delete();
                    $user->delete();
                    $this->error("executive {$check['name']} Blade missing: {$needle}");

                    return self::FAILURE;
                }
            }
            $this->info("executive {$check['name']} Blade OK");
        }

        $register = $dashboard->register('critical');
        if (count($register['tickets']) < 1) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('executive register service missing critical tickets');

            return self::FAILURE;
        }

        $registerHtml = view('executive.register', [
            'user' => $identity,
            'activeNav' => 'register',
            'title' => 'Critical risks',
            'pageDesc' => 'Extreme/Critical risk reports prioritized for executive oversight.',
            'emptyMessage' => 'No critical risk reports at this time.',
            'stats' => $register['stats'],
            'tickets' => $register['tickets'],
            'filters' => $register['filters'],
            'categories' => $register['categories'],
            'flash' => null,
            'error' => null,
        ])->render();

        if (! str_contains($registerHtml, 'Critical risks') || ! str_contains($registerHtml, $ticket->reference)) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('executive register Blade missing expected content');

            return self::FAILURE;
        }
        if (str_contains($registerHtml, '/laravel/executive')) {
            Auth::logout();
            $ticket->delete();
            $user->delete();
            $this->error('executive register still uses /laravel prefix');

            return self::FAILURE;
        }
        $this->info('executive register Blade OK');

        Auth::logout();
        $ticket->delete();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
