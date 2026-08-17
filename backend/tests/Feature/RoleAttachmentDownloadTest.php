<?php

namespace Tests\Feature;

use App\Models\RiskTicket;
use App\Models\User;
use App\Services\AttachmentService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoleAttachmentDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_phase_eight_slice_nine(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 12)
            ->assertJsonPath('slice', 1);
    }

    public function test_guest_cannot_download_role_attachment(): void
    {
        Storage::fake('evidence');
        [$id] = $this->seedTicketAndFile('reporter1');

        $this->get('/dept/attachments/'.$id)->assertRedirect();
        $this->get('/supervisor/attachments/'.$id)->assertRedirect();
    }

    public function test_each_console_role_can_download_visible_attachment(): void
    {
        Storage::fake('evidence');
        [$id, $reporter] = $this->seedTicketAndFile('reporter-att');

        $cases = [
            [$reporter, '/supervisor/attachments/'],
            [User::factory()->create([
                'role' => Roles::DEPT_HEAD,
                'role_label' => Roles::label(Roles::DEPT_HEAD),
                'department' => 'Information Technology',
            ]), '/dept/attachments/'],
            [User::factory()->create([
                'role' => Roles::RM_OFFICER,
                'role_label' => Roles::label(Roles::RM_OFFICER),
            ]), '/officer/attachments/'],
            [User::factory()->create([
                'role' => Roles::EXECUTIVE,
                'role_label' => Roles::label(Roles::EXECUTIVE),
            ]), '/executive/attachments/'],
            [User::factory()->create([
                'role' => Roles::PRESIDENT,
                'role_label' => Roles::label(Roles::PRESIDENT),
            ]), '/president/attachments/'],
        ];

        foreach ($cases as [$user, $prefix]) {
            $this->actingAs($user)
                ->get($prefix.$id)
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_reporter_cannot_download_another_users_attachment(): void
    {
        Storage::fake('evidence');
        [$id] = $this->seedTicketAndFile('owner-att');
        $other = User::factory()->create([
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
        ]);

        $this->actingAs($other)
            ->get('/supervisor/attachments/'.$id)
            ->assertNotFound();
    }

    /**
     * @return array{0: string, 1: User}
     */
    private function seedTicketAndFile(string $username): array
    {
        $reporter = User::factory()->create([
            'username' => $username,
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
            'department' => 'Information Technology',
        ]);
        RiskTicket::query()->create([
            'external_id' => 'ext-RISK-ATT-1',
            'reference' => 'RISK-ATT-1',
            'title' => 'Attachment ticket',
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
        $att = app(AttachmentService::class)->storeRawFile(
            'RISK-ATT-1',
            'smoke.pdf',
            'application/pdf',
            '%PDF-1.4 test',
            $reporter->username,
        );

        return [$att->id, $reporter];
    }
}
