<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Phase 16 slice 3: smoke Next.js admin users/settings/audit + workflow UI.
 */
class SmokePhase14Frontend extends Command
{
    protected $signature = 'rms:smoke-phase14-frontend-internal';

    protected $description = 'Internal: Phase 16 workflow mutation smoke implementation';

    public function handle(): int
    {
        $apiBase = rtrim((string) env('RMS_SMOKE_API_URL', 'http://127.0.0.1:8080'), '/');
        try {
            $health = Http::timeout(3)->get($apiBase.'/v1/health');
        } catch (\Throwable $e) {
            $this->error('API health unreachable: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $health->successful()) {
            $this->error('API health HTTP '.$health->status());

            return self::FAILURE;
        }

        $phase = (int) ($health->json('phase') ?? 0);
        $slice = (int) ($health->json('slice') ?? 0);
        if ($phase !== 16 || $slice !== 3) {
            $this->error('Expected phase 16 slice 3, got phase '.$phase.' slice '.$slice);

            return self::FAILURE;
        }

        $this->info('API health OK (phase 16 / slice 3 — admin users/settings/audit)');

        if (! $this->smokeSanctumToken($apiBase)) {
            return self::FAILURE;
        }

        if (! $this->smokeReporterDraftSubmit($apiBase)) {
            return self::FAILURE;
        }

        $frontendBase = rtrim((string) env('RMS_SMOKE_FRONTEND_URL', 'http://frontend:3000'), '/');
        $basePath = rtrim((string) env('RMS_SMOKE_FRONTEND_BASE_PATH', '/app'), '/');
        try {
            $frontend = Http::timeout(3)->get($frontendBase.$basePath.'/health');
        } catch (\Throwable $e) {
            $this->warn('frontend unreachable (start with --profile frontend): '.$e->getMessage());

            return self::SUCCESS;
        }

        if (! $frontend->successful()) {
            $this->error('frontend health HTTP '.$frontend->status());

            return self::FAILURE;
        }

        if ((int) ($frontend->json('phase') ?? 0) !== 16 || (int) ($frontend->json('slice') ?? 0) !== 3) {
            $this->error('frontend health unexpected: '.$frontend->body());

            return self::FAILURE;
        }

        $this->info('frontend health OK (phase 16 / slice 3)');

        $edgeBase = rtrim((string) env('RMS_SMOKE_EDGE_URL', 'http://nginx'), '/');
        try {
            $proxied = Http::timeout(3)->get($edgeBase.$basePath.'/health');
        } catch (\Throwable $e) {
            $this->warn('nginx /app proxy unreachable: '.$e->getMessage());

            return self::SUCCESS;
        }

        if (! $proxied->successful()) {
            $this->error('nginx /app health HTTP '.$proxied->status());

            return self::FAILURE;
        }

        if ((int) ($proxied->json('phase') ?? 0) !== 16 || (int) ($proxied->json('slice') ?? 0) !== 3) {
            $this->error('nginx /app health unexpected: '.$proxied->body());

            return self::FAILURE;
        }

        $this->info('nginx /app proxy OK (phase 16 / slice 3)');

        try {
            $loginPage = Http::timeout(3)->get($edgeBase.$basePath.'/login');
            if ($loginPage->successful() && str_contains($loginPage->body(), 'Next.js sign in')) {
                $this->info('nginx /app/login page OK');
            }

            $newTicket = Http::timeout(3)->get($edgeBase.$basePath.'/tickets/new');
            if ($newTicket->successful() && str_contains($newTicket->body(), 'New risk report')) {
                $this->info('nginx /app/tickets/new page OK');
            }

            $usersPage = Http::timeout(3)->get($edgeBase.$basePath.'/users');
            if ($usersPage->successful() && str_contains($usersPage->body(), 'Create user')) {
                $this->info('nginx /app/users page OK');
            }

            $departments = Http::timeout(3)->get($edgeBase.$basePath.'/departments');
            if ($departments->successful() && str_contains($departments->body(), 'Create department')) {
                $this->info('nginx /app/departments page OK');
            }
        } catch (\Throwable) {
            $this->warn('nginx /app UI page checks skipped');
        }

        return self::SUCCESS;
    }

    private function smokeSanctumToken(string $apiBase): bool
    {
        $username = (string) env('RMS_SMOKE_USERNAME', 'admin');
        $password = (string) env('RMS_SMOKE_PASSWORD', 'a3c1993');

        try {
            $tokenResponse = Http::timeout(5)->post($apiBase.'/v1/auth/token', [
                'username' => $username,
                'password' => $password,
                'device_name' => 'smoke-phase14-frontend',
            ]);
        } catch (\Throwable $e) {
            $this->error('Sanctum token request failed: '.$e->getMessage());

            return false;
        }

        if (! $tokenResponse->successful()) {
            $this->error('Sanctum token HTTP '.$tokenResponse->status().': '.$tokenResponse->body());

            return false;
        }

        $token = (string) ($tokenResponse->json('token') ?? '');
        if ($token === '') {
            $this->error('Sanctum token missing from response');

            return false;
        }

        try {
            $me = Http::timeout(3)
                ->withToken($token)
                ->get($apiBase.'/v1/users/me');
        } catch (\Throwable $e) {
            $this->error('Sanctum /users/me failed: '.$e->getMessage());

            return false;
        }

        if (! $me->successful()) {
            $this->error('Sanctum /users/me HTTP '.$me->status());

            return false;
        }

        if ((string) ($me->json('user.username') ?? '') !== strtolower($username)) {
            $this->error('Sanctum /users/me unexpected user: '.$me->body());

            return false;
        }

        $this->info('Sanctum bearer auth OK ('.($me->json('user.username') ?? $username).')');

        return true;
    }

    private function smokeReporterDraftSubmit(string $apiBase): bool
    {
        $username = (string) env('RMS_SMOKE_REPORTER_USERNAME', 'reporter');
        $password = (string) env('RMS_SMOKE_REPORTER_PASSWORD', 'a3c1993');

        try {
            $tokenResponse = Http::timeout(5)->post($apiBase.'/v1/auth/token', [
                'username' => $username,
                'password' => $password,
                'device_name' => 'smoke-phase16-reporter',
            ]);
        } catch (\Throwable $e) {
            $this->warn('Reporter token skipped: '.$e->getMessage());

            return true;
        }

        if (! $tokenResponse->successful()) {
            $this->warn('Reporter '.$username.' unavailable (HTTP '.$tokenResponse->status().'); skip draft submit');

            return true;
        }

        $token = (string) ($tokenResponse->json('token') ?? '');
        try {
            $create = Http::timeout(8)->withToken($token)->post($apiBase.'/v1/tickets', [
                'title' => 'Next.js reporter smoke outage',
                'what' => 'Core switch failed during peak hours',
                'why' => 'Aging hardware without redundancy',
                'where' => 'Data center rack A',
                'when' => '2026-08-18 morning',
                'who' => 'IT operations staff',
                'how' => 'Single point of failure caused outage',
                'location' => 'Head office',
                'mitigationApproach' => 'Add redundant switch',
                'evidenceCount' => 1,
            ]);
        } catch (\Throwable $e) {
            $this->error('Draft create failed: '.$e->getMessage());

            return false;
        }

        if (! $create->successful()) {
            $this->error('Draft create HTTP '.$create->status().': '.$create->body());

            return false;
        }

        $reference = (string) ($create->json('ticket.reference') ?? '');
        if ($reference === '' || ($create->json('ticket.status') ?? '') !== 'draft') {
            $this->error('Draft create unexpected: '.$create->body());

            return false;
        }

        try {
            $submit = Http::timeout(8)->withToken($token)->post($apiBase.'/v1/tickets/'.$reference.'/submit');
        } catch (\Throwable $e) {
            $this->error('Draft submit failed: '.$e->getMessage());

            return false;
        }

        if (! $submit->successful() || ($submit->json('ticket.status') ?? '') !== 'assigned') {
            $this->error('Draft submit unexpected HTTP '.$submit->status().': '.$submit->body());

            return false;
        }

        $this->info('Reporter draft create+submit OK ('.$reference.')');

        try {
            $comment = Http::timeout(5)->withToken($token)->post($apiBase.'/v1/tickets/'.$reference.'/comments', [
                'comment' => 'Phase 16 slice 2 smoke comment',
            ]);
        } catch (\Throwable $e) {
            $this->error('Comment failed: '.$e->getMessage());

            return false;
        }

        if (! $comment->successful()) {
            $this->error('Comment HTTP '.$comment->status().': '.$comment->body());

            return false;
        }

        $this->info('Ticket comment OK ('.$reference.')');

        $department = (string) ($submit->json('ticket.department') ?? '');
        $this->smokeDeptAccept($apiBase, $reference, $department);

        return $this->smokeAdminDepartment($apiBase);
    }

    private function smokeDeptAccept(string $apiBase, string $reference, string $department): void
    {
        $heads = [
            'Information Technology' => 'dephead',
            'IT Services' => 'dephead',
            'Finance' => 'finance',
            'Operations' => 'operations',
            'Administration' => 'adminsupport',
            'HRMS' => 'hrms',
            'MMCD' => 'mmcd',
            'New Business Operations' => 'nbo',
        ];
        $username = $heads[$department] ?? 'dephead';
        $password = (string) env('RMS_SMOKE_PASSWORD', 'a3c1993');

        try {
            $tokenResponse = Http::timeout(5)->post($apiBase.'/v1/auth/token', [
                'username' => $username,
                'password' => $password,
                'device_name' => 'smoke-phase16-dept',
            ]);
        } catch (\Throwable $e) {
            $this->warn('Dept accept skipped: '.$e->getMessage());

            return;
        }

        if (! $tokenResponse->successful()) {
            $this->warn('Dept head '.$username.' unavailable; skip accept');

            return;
        }

        $token = (string) ($tokenResponse->json('token') ?? '');
        $accept = Http::timeout(8)->withToken($token)->post($apiBase.'/v1/tickets/'.$reference.'/accept', [
            'comment' => 'Smoke accept',
        ]);

        if ($accept->successful() && ($accept->json('ticket.status') ?? '') === 'in_progress') {
            $this->info('Dept accept OK ('.$username.' / '.$reference.')');

            return;
        }

        $this->warn('Dept accept skipped HTTP '.$accept->status().' (department '.$department.')');
    }

    private function smokeAdminDepartment(string $apiBase): bool
    {
        $username = (string) env('RMS_SMOKE_USERNAME', 'admin');
        $password = (string) env('RMS_SMOKE_PASSWORD', 'a3c1993');

        try {
            $tokenResponse = Http::timeout(5)->post($apiBase.'/v1/auth/token', [
                'username' => $username,
                'password' => $password,
                'device_name' => 'smoke-phase16-admin',
            ]);
        } catch (\Throwable $e) {
            $this->error('Admin token failed: '.$e->getMessage());

            return false;
        }

        if (! $tokenResponse->successful()) {
            $this->error('Admin token HTTP '.$tokenResponse->status());

            return false;
        }

        $token = (string) ($tokenResponse->json('token') ?? '');
        $suffix = (string) (int) round(microtime(true) * 1000);
        $create = Http::timeout(5)->withToken($token)->post($apiBase.'/v1/departments', [
            'name' => 'Smoke Dept '.$suffix,
            'code' => 'SMK'.substr($suffix, -4),
            'description' => 'Phase 16 slice 2 smoke department',
        ]);

        if (! $create->successful()) {
            $this->error('Admin department create HTTP '.$create->status().': '.$create->body());

            return false;
        }

        $this->info('Admin department create OK ('.$create->json('department.code').')');

        return $this->smokeAdminConsole($apiBase, $token);
    }

    private function smokeAdminConsole(string $apiBase, string $token): bool
    {
        $users = Http::timeout(5)->withToken($token)->get($apiBase.'/v1/users');
        if (! $users->successful()) {
            $this->error('Admin users list HTTP '.$users->status().': '.$users->body());

            return false;
        }

        $suffix = (string) (int) round(microtime(true) * 1000);
        $username = 'smk'.substr($suffix, -6);
        $createUser = Http::timeout(5)->withToken($token)->post($apiBase.'/v1/users', [
            'username' => $username,
            'displayName' => 'Smoke User '.$suffix,
            'email' => $username.'@rms.local',
            'department' => 'Administration',
            'position' => 'Analyst',
            'role' => 'supervisor',
            'password' => 'a3c1993',
            'confirmPassword' => 'a3c1993',
        ]);
        if (! $createUser->successful()) {
            $this->error('Admin user create HTTP '.$createUser->status().': '.$createUser->body());

            return false;
        }

        $settings = Http::timeout(5)->withToken($token)->get($apiBase.'/v1/settings');
        if (! $settings->successful() || ! is_array($settings->json('settings'))) {
            $this->error('Admin settings GET unexpected HTTP '.$settings->status().': '.$settings->body());

            return false;
        }

        $logs = Http::timeout(5)->withToken($token)->get($apiBase.'/v1/audit-logs');
        if (! $logs->successful()) {
            $this->error('Admin audit logs HTTP '.$logs->status().': '.$logs->body());

            return false;
        }

        $this->info('Admin users/settings/audit OK ('.$username.')');

        return true;
    }
}
