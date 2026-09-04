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
            ->assertJsonPath('phase', 16)
            ->assertJsonPath('slice', 3);
    }

    public function test_executive_comment_route_removed(): void
    {
        $this->submittedTicket('RISK-TEST-E001');

        $this->post('/executive/tickets/RISK-TEST-E001/comment', [
            'comment' => 'Guest note',
        ])->assertNotFound();
    }

    public function test_executive_cannot_post_comment(): void
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
            ->assertNotFound();

        $ticket->refresh();
        $this->assertSame([], $ticket->thread_comments ?? []);
    }

    private function submittedTicket(string $ref): RiskTicket
    {
        return RiskTicket::query()->create([
            'external_id' => 'ext-'.$ref,
            'reference' => $ref,
            'title' => 'Exec ticket',
            'status' => 'assigned',
            'submitted_by' => 'reporter',
            'department' => 'Information Technology',
            'deleted' => false,
            'thread_comments' => [],
            'source_updated_at' => now(),
        ]);
    }
}
