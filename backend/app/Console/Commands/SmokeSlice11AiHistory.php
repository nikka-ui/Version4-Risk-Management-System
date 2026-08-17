<?php

namespace App\Console\Commands;

use App\Models\AiAnalysisResult;
use App\Models\User;
use App\Services\AdminAiAnalysisService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 11 slice 3: smoke admin AI history Blade + list service.
 */
class SmokeSlice11AiHistory extends Command
{
    protected $signature = 'rms:smoke-slice11-ai-history';

    protected $description = 'Smoke admin AI analysis history Blade page';

    public function handle(AdminAiAnalysisService $service): int
    {
        $ref = 'RISK-SMOKE-AIH-'.strtoupper(bin2hex(random_bytes(2)));
        $row = AiAnalysisResult::query()->create([
            'ticket_reference' => $ref,
            'source' => 'php-stub',
            'risk_category' => 'technological',
            'likelihood' => 3,
            'impact' => 4,
            'severity' => 4,
            'confidence' => 0.82,
            'responsible_department' => 'Information Technology',
            'priority' => 'high',
            'input' => ['title' => 'Slice11 history smoke'],
            'result' => [
                'summary' => 'Slice11 AI history smoke',
                'source' => 'php-stub',
            ],
        ]);

        $username = 'smoke_aih_'.bin2hex(random_bytes(3));
        $user = User::query()->create([
            'username' => $username,
            'name' => 'Smoke AI History',
            'email' => "{$username}@rms.local",
            'password' => 'SmokeAih11!',
            'role' => Roles::ADMIN,
            'role_label' => Roles::label(Roles::ADMIN),
            'department' => 'Administration',
            'position' => 'System Administrator',
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);

        Auth::login($user);
        $payload = $service->list(null, null, null, $ref);
        $found = collect($payload['runs'] ?? [])->first(
            fn ($run) => is_array($run) && ($run['summary'] ?? '') === 'Slice11 AI history smoke'
        );
        if (! is_array($found)) {
            Auth::logout();
            $row->delete();
            $user->delete();
            $this->error('list did not return smoke AI row');

            return self::FAILURE;
        }
        $this->info('AI history list OK');

        $html = view('admin.ai-analysis', [
            'user' => $user->toIdentityArray(),
            'activeNav' => 'ai',
            'title' => 'AI Analysis History',
            'runs' => $payload['runs'],
            'options' => $payload['options'],
            'filters' => $payload['filters'],
            'flash' => null,
            'error' => null,
        ])->render();

        if (! str_contains($html, 'AI Analysis History') || ! str_contains($html, 'Slice11 AI history smoke')) {
            Auth::logout();
            $row->delete();
            $user->delete();
            $this->error('admin AI history Blade missing expected content');

            return self::FAILURE;
        }
        $this->info('admin AI history Blade OK');

        Auth::logout();
        $row->delete();
        $user->delete();
        $this->info('cleanup OK');

        return self::SUCCESS;
    }
}
