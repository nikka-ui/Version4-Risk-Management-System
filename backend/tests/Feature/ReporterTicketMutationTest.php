<?php

namespace Tests\Feature;

use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporterTicketMutationTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_phase_eight_slice_three(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 8)
            ->assertJsonPath('slice', 3);
    }

    public function test_guest_cannot_submit_or_delete_draft(): void
    {
        $this->draftTicket('RISK-TEST-R001', 'reporter1');

        $this->post('/supervisor/tickets/new/preview/RISK-TEST-R001/submit', [
            'confirmBox' => '1',
        ])->assertRedirect();
        $this->post('/supervisor/tickets/RISK-TEST-R001/delete')->assertRedirect();

        $this->assertSame('draft', RiskTicket::query()->where('reference', 'RISK-TEST-R001')->value('status'));
        $this->assertTrue(RiskTicket::query()->where('reference', 'RISK-TEST-R001')->exists());
    }

    public function test_reporter_can_save_submit_and_delete_draft(): void
    {
        $reporter = User::factory()->create([
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
            'department' => 'Information Technology',
        ]);

        $ticketA = $this->draftTicket('RISK-TEST-R002', $reporter->username);
        $ticketB = $this->draftTicket('RISK-TEST-R003', $reporter->username);

        $this->actingAs($reporter)
            ->post('/supervisor/tickets/new/preview/RISK-TEST-R002/save')
            ->assertRedirect();
        $ticketA->refresh();
        $this->assertSame('draft', $ticketA->status);

        $this->actingAs($reporter)
            ->post('/supervisor/tickets/new/preview/RISK-TEST-R002/submit', [
                'confirmBox' => '1',
            ])
            ->assertRedirect();
        $ticketA->refresh();
        $this->assertSame('assigned', $ticketA->status);
        $this->assertSame('pending', $ticketA->ownership['state'] ?? null);

        $this->actingAs($reporter)
            ->post('/supervisor/tickets/RISK-TEST-R003/delete')
            ->assertRedirect();
        $this->assertFalse(RiskTicket::query()->where('reference', 'RISK-TEST-R003')->exists());
    }

    public function test_submit_requires_confirmation(): void
    {
        $reporter = User::factory()->create([
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
        ]);
        $ticket = $this->draftTicket('RISK-TEST-R004', $reporter->username);

        $this->actingAs($reporter)
            ->post('/supervisor/tickets/new/preview/RISK-TEST-R004/submit')
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('draft', $ticket->status);
    }

    public function test_non_reporter_cannot_submit_draft(): void
    {
        $head = User::factory()->create([
            'role' => Roles::DEPT_HEAD,
            'role_label' => Roles::label(Roles::DEPT_HEAD),
        ]);
        $this->draftTicket('RISK-TEST-R005', 'reporter1');

        $this->actingAs($head)
            ->post('/supervisor/tickets/new/preview/RISK-TEST-R005/submit', [
                'confirmBox' => '1',
            ])
            ->assertRedirect();

        $this->assertSame('draft', RiskTicket::query()->where('reference', 'RISK-TEST-R005')->value('status'));
    }

    private function draftTicket(string $ref, string $username): RiskTicket
    {
        return RiskTicket::query()->create([
            'external_id' => 'ext-'.$ref,
            'reference' => $ref,
            'title' => $ref,
            'description' => $ref,
            'location' => 'HQ',
            'status' => 'draft',
            'submitted_by' => $username,
            'evidence_count' => 1,
            'deleted' => false,
            'five_w1h' => [
                'what' => 'what',
                'why' => 'why',
                'where' => 'where',
                'when' => 'when',
                'who' => 'who',
                'how' => 'how',
            ],
        ]);
    }
}
