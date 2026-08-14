<?php

namespace Tests\Feature;

use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutiveTicketMutationTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_phase_seven_slice_thirteen(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 9)
            ->assertJsonPath('slice', 6);
    }

    public function test_guest_cannot_post_executive_comment(): void
    {
        $this->submittedTicket('RISK-TEST-E001');

        $this->post('/executive/tickets/RISK-TEST-E001/comment', [
            'comment' => 'Guest note',
        ])->assertRedirect();

        $ticket = RiskTicket::query()->where('reference', 'RISK-TEST-E001')->first();
        $this->assertSame([], $ticket?->thread_comments ?? []);
    }

    public function test_executive_can_post_comment_and_reply(): void
    {
        $executive = User::factory()->create([
            'role' => Roles::EXECUTIVE,
            'role_label' => Roles::label(Roles::EXECUTIVE),
            'position' => 'Executive Committee',
        ]);
        $ticket = $this->submittedTicket('RISK-TEST-E002');

        $this->actingAs($executive)
            ->post('/executive/tickets/RISK-TEST-E002/comment', [
                'comment' => 'Oversight guidance',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $thread = $ticket->thread_comments ?? [];
        $feed = $ticket->executive_comments ?? [];
        $this->assertCount(1, $thread);
        $this->assertSame('Oversight guidance', $thread[0]['body'] ?? null);
        $this->assertSame(Roles::EXECUTIVE, $thread[0]['authorRole'] ?? null);
        $this->assertSame('Oversight guidance', $feed[0]['body'] ?? null);

        $this->actingAs($executive)
            ->post('/executive/tickets/RISK-TEST-E002/comment', [
                'comment' => 'Follow-up',
                'parentId' => $thread[0]['id'],
            ])
            ->assertRedirect();

        $ticket->refresh();
        $thread = $ticket->thread_comments ?? [];
        $this->assertCount(2, $thread);
        $this->assertSame($thread[0]['id'], $thread[1]['parentId'] ?? null);
        $this->assertSame('Follow-up', $thread[1]['body'] ?? null);
    }

    public function test_executive_empty_comment_is_rejected(): void
    {
        $executive = User::factory()->create([
            'role' => Roles::EXECUTIVE,
            'role_label' => Roles::label(Roles::EXECUTIVE),
        ]);
        $ticket = $this->submittedTicket('RISK-TEST-E003');

        $this->actingAs($executive)
            ->post('/executive/tickets/RISK-TEST-E003/comment', [
                'comment' => '   ',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame([], $ticket->thread_comments ?? []);
    }

    public function test_non_executive_cannot_post_comment(): void
    {
        $officer = User::factory()->create([
            'role' => Roles::RM_OFFICER,
            'role_label' => Roles::label(Roles::RM_OFFICER),
        ]);
        $this->submittedTicket('RISK-TEST-E004');

        $this->actingAs($officer)
            ->post('/executive/tickets/RISK-TEST-E004/comment', [
                'comment' => 'Officer note',
            ])
            ->assertRedirect();

        $ticket = RiskTicket::query()->where('reference', 'RISK-TEST-E004')->first();
        $this->assertSame([], $ticket?->thread_comments ?? []);
    }

    private function submittedTicket(string $ref): RiskTicket
    {
        return RiskTicket::query()->create([
            'external_id' => 'ext-'.$ref,
            'reference' => $ref,
            'title' => $ref,
            'status' => 'submitted',
            'likelihood' => 3,
            'impact' => 3,
            'submitted_by' => 'reporter1',
            'thread_comments' => [],
            'executive_comments' => [],
            'deleted' => false,
        ]);
    }
}
