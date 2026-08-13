<?php

namespace Tests\Feature;

use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresidentTicketMutationTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_phase_seven_slice_thirteen(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 8)
            ->assertJsonPath('slice', 3);
    }

    public function test_guest_cannot_record_president_decision(): void
    {
        $this->pendingPlanTicket('RISK-TEST-P001');

        $this->post('/president/tickets/RISK-TEST-P001/decision', [
            'decision' => 'approve',
        ])->assertRedirect();

        $this->assertSame('pending_president', RiskTicket::query()->where('reference', 'RISK-TEST-P001')->value('status'));
    }

    public function test_president_can_approve_action_plan_and_close_final(): void
    {
        $president = User::factory()->create([
            'role' => Roles::PRESIDENT,
            'role_label' => Roles::label(Roles::PRESIDENT),
        ]);

        $plan = $this->pendingPlanTicket('RISK-TEST-P002');
        $final = $this->finalTicket('RISK-TEST-P003');

        $this->actingAs($president)
            ->post('/president/tickets/RISK-TEST-P002/decision', [
                'decision' => 'approve',
                'note' => 'Proceed',
            ])
            ->assertRedirect();
        $plan->refresh();
        $this->assertSame('in_mitigation', $plan->status);
        $this->assertSame('approve', $plan->president_plan_decision['decisionId'] ?? null);

        $this->actingAs($president)
            ->post('/president/tickets/RISK-TEST-P003/decision', [
                'decision' => 'close',
                'note' => 'Done',
            ])
            ->assertRedirect();
        $final->refresh();
        $this->assertSame('closed', $final->status);
        $this->assertSame('close', $final->president_final_decision['decisionId'] ?? null);
    }

    public function test_president_return_requires_note(): void
    {
        $president = User::factory()->create([
            'role' => Roles::PRESIDENT,
            'role_label' => Roles::label(Roles::PRESIDENT),
        ]);
        $ticket = $this->pendingPlanTicket('RISK-TEST-P004');

        $this->actingAs($president)
            ->post('/president/tickets/RISK-TEST-P004/decision', [
                'decision' => 'return',
                'note' => '   ',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('pending_president', $ticket->status);
    }

    public function test_guest_cannot_post_president_comment(): void
    {
        $this->pendingPlanTicket('RISK-TEST-P006');

        $this->post('/president/tickets/RISK-TEST-P006/comment', [
            'comment' => 'Guest note',
        ])->assertRedirect();

        $ticket = RiskTicket::query()->where('reference', 'RISK-TEST-P006')->first();
        $this->assertSame([], $ticket?->thread_comments ?? []);
    }

    public function test_president_can_post_comment_on_critical_ticket(): void
    {
        $president = User::factory()->create([
            'role' => Roles::PRESIDENT,
            'role_label' => Roles::label(Roles::PRESIDENT),
        ]);
        $ticket = $this->pendingPlanTicket('RISK-TEST-P007');

        $this->actingAs($president)
            ->post('/president/tickets/RISK-TEST-P007/comment', [
                'comment' => 'Presidential note',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $thread = $ticket->thread_comments ?? [];
        $feed = $ticket->executive_comments ?? [];
        $this->assertCount(1, $thread);
        $this->assertSame('Presidential note', $thread[0]['body'] ?? null);
        $this->assertSame(Roles::PRESIDENT, $thread[0]['authorRole'] ?? null);
        $this->assertSame('Presidential note', $feed[0]['body'] ?? null);
    }

    public function test_president_empty_comment_is_rejected(): void
    {
        $president = User::factory()->create([
            'role' => Roles::PRESIDENT,
            'role_label' => Roles::label(Roles::PRESIDENT),
        ]);
        $ticket = $this->pendingPlanTicket('RISK-TEST-P008');

        $this->actingAs($president)
            ->post('/president/tickets/RISK-TEST-P008/comment', [
                'comment' => '   ',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame([], $ticket->thread_comments ?? []);
    }

    public function test_president_cannot_comment_on_moderate_ticket(): void
    {
        $president = User::factory()->create([
            'role' => Roles::PRESIDENT,
            'role_label' => Roles::label(Roles::PRESIDENT),
        ]);
        $ticket = RiskTicket::query()->create([
            'external_id' => 'ext-RISK-TEST-P009',
            'reference' => 'RISK-TEST-P009',
            'title' => 'RISK-TEST-P009',
            'status' => 'submitted',
            'likelihood' => 2,
            'impact' => 2,
            'ai' => ['riskLevel' => ['id' => 'moderate', 'label' => 'Moderate']],
            'submitted_by' => 'reporter1',
            'thread_comments' => [],
            'deleted' => false,
        ]);

        $this->actingAs($president)
            ->post('/president/tickets/RISK-TEST-P009/comment', [
                'comment' => 'Out of scope',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame([], $ticket->thread_comments ?? []);
    }

    public function test_non_president_cannot_post_comment(): void
    {
        $officer = User::factory()->create([
            'role' => Roles::RM_OFFICER,
            'role_label' => Roles::label(Roles::RM_OFFICER),
        ]);
        $this->pendingPlanTicket('RISK-TEST-P010');

        $this->actingAs($officer)
            ->post('/president/tickets/RISK-TEST-P010/comment', [
                'comment' => 'Officer note',
            ])
            ->assertRedirect();

        $ticket = RiskTicket::query()->where('reference', 'RISK-TEST-P010')->first();
        $this->assertSame([], $ticket?->thread_comments ?? []);
    }

    public function test_non_president_cannot_record_decision(): void
    {
        $officer = User::factory()->create([
            'role' => Roles::RM_OFFICER,
            'role_label' => Roles::label(Roles::RM_OFFICER),
        ]);
        $this->pendingPlanTicket('RISK-TEST-P005');

        $this->actingAs($officer)
            ->post('/president/tickets/RISK-TEST-P005/decision', [
                'decision' => 'approve',
            ])
            ->assertRedirect();

        $this->assertSame('pending_president', RiskTicket::query()->where('reference', 'RISK-TEST-P005')->value('status'));
    }

    private function pendingPlanTicket(string $ref): RiskTicket
    {
        return RiskTicket::query()->create([
            'external_id' => 'ext-'.$ref,
            'reference' => $ref,
            'title' => $ref,
            'status' => 'pending_president',
            'likelihood' => 5,
            'impact' => 5,
            'ai' => ['riskLevel' => ['id' => 'critical', 'label' => 'Critical']],
            'action_plan' => [
                'summary' => 'Emergency mitigation',
                'steps' => ['Replace hardware'],
                'submittedForReviewAt' => now()->toIso8601String(),
            ],
            'submitted_by' => 'reporter1',
            'deleted' => false,
        ]);
    }

    private function finalTicket(string $ref): RiskTicket
    {
        return RiskTicket::query()->create([
            'external_id' => 'ext-'.$ref,
            'reference' => $ref,
            'title' => $ref,
            'status' => 'pending_president_final',
            'likelihood' => 5,
            'impact' => 5,
            'ai' => ['riskLevel' => ['id' => 'critical', 'label' => 'Critical']],
            'submitted_by' => 'reporter1',
            'deleted' => false,
        ]);
    }
}
