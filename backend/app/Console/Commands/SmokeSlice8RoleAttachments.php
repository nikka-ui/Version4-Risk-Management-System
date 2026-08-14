<?php

namespace App\Console\Commands;

use App\Http\Controllers\RoleAttachmentController;
use App\Models\RiskTicket;
use App\Models\User;
use App\Services\AttachmentService;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 8 slice 9: smoke role-console attachment downloads.
 */
class SmokeSlice8RoleAttachments extends Command
{
    protected $signature = 'rms:smoke-slice8-role-attachments';

    protected $description = 'Smoke Laravel GET /{role}/attachments/:id downloads';

    public function handle(RoleAttachmentController $controller, AttachmentService $attachments): int
    {
        Storage::fake('evidence');

        $suffix = bin2hex(random_bytes(2));
        $ref = 'RISK-SMOKE-ATT-'.strtoupper($suffix);
        $reporter = $this->makeUser('smoke_ratt_'.$suffix, Roles::SUPERVISOR, 'Information Technology');
        $ticket = RiskTicket::query()->create([
            'external_id' => 'ext-'.$ref,
            'reference' => $ref,
            'title' => 'Smoke attachment',
            'status' => 'assigned',
            'department' => 'Information Technology',
            'submitted_by' => $reporter->username,
            'deleted' => false,
            'likelihood' => 5,
            'impact' => 5,
            'ai' => ['riskLevel' => ['id' => 'critical']],
            'ownership' => [
                'state' => 'pending',
                'ownerDepartment' => 'Information Technology',
            ],
        ]);
        $att = $attachments->storeRawFile(
            $ref,
            'smoke.pdf',
            'application/pdf',
            '%PDF-1.4 smoke',
            $reporter->username,
        );

        $roles = [
            [Roles::SUPERVISOR, 'Information Technology', '/supervisor/attachments/'],
            [Roles::DEPT_HEAD, 'Information Technology', '/dept/attachments/'],
            [Roles::RM_OFFICER, null, '/officer/attachments/'],
            [Roles::EXECUTIVE, null, '/executive/attachments/'],
            [Roles::PRESIDENT, null, '/president/attachments/'],
        ];

        try {
            foreach ($roles as [$role, $department, $prefix]) {
                $user = $role === Roles::SUPERVISOR
                    ? $reporter
                    : $this->makeUser('smoke_ratt_'.$role.'_'.$suffix, $role, $department);
                Auth::login($user);
                try {
                    $request = Request::create($prefix.$att->id, 'GET');
                    $request->setUserResolver(fn () => Auth::user());
                    $response = $controller->download($request, $att->id);
                    $body = '';
                    ob_start();
                    $response->sendContent();
                    $body = (string) ob_get_clean();
                    if (
                        $response->getStatusCode() !== 200
                        || ! str_contains((string) $response->headers->get('Content-Type'), 'application/pdf')
                        || ! str_contains($body, '%PDF-1.4 smoke')
                    ) {
                        $this->error($role.' attachment download failed');

                        return self::FAILURE;
                    }
                    $this->info($role.' attachment download OK');
                } finally {
                    Auth::logout();
                    if ($user->id !== $reporter->id) {
                        $user->delete();
                    }
                }
            }
        } finally {
            $attachments->deleteWithStorage($att->id);
            $ticket->delete();
            $reporter->delete();
        }

        $this->info('cleanup OK');

        return self::SUCCESS;
    }

    private function makeUser(string $username, string $role, ?string $department): User
    {
        return User::query()->create([
            'username' => $username,
            'name' => 'Smoke Attach '.$role,
            'email' => $username.'@rms.local',
            'password' => 'SmokeAtt1!',
            'role' => $role,
            'role_label' => Roles::label($role),
            'department' => $department,
            'position' => Roles::label($role),
            'active' => true,
            'status' => 'active',
            'deleted' => false,
        ]);
    }
}
