<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficerTicketMutationTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_phase_seven_slice_thirteen(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 8)
            ->assertJsonPath('slice', 3);
    }

    public function test_guest_cannot_reopen_ticket(): void
    {
        $this->closedTicket('RISK-TEST-O001', 'Information Technology');

        $this->post('/officer/tickets/RISK-TEST-O001/reopen', [
            'reason' => 'Nope',
            'department' => 'Finance',
        ])->assertRedirect();

        $this->assertSame('closed', RiskTicket::query()->where('reference', 'RISK-TEST-O001')->value('status'));
    }

    public function test_officer_can_reopen_closed_ticket(): void
    {
        Department::query()->create([
            'external_id' => 'dept-it',
            'name' => 'Information Technology',
            'code' => 'IT',
            'active' => true,
            'status' => 'active',
        ]);
        Department::query()->create([
            'external_id' => 'dept-fin',
            'name' => 'Finance',
            'code' => 'FIN',
            'active' => true,
            'status' => 'active',
        ]);

        $officer = User::factory()->create([
            'role' => Roles::RM_OFFICER,
            'role_label' => Roles::label(Roles::RM_OFFICER),
        ]);
        $ticket = $this->closedTicket('RISK-TEST-O002', 'Information Technology');

        $this->actingAs($officer)
            ->post('/officer/tickets/RISK-TEST-O002/reopen', [
                'reason' => 'Need another look',
                'department' => 'Finance',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('assigned', $ticket->status);
        $this->assertSame('Finance', $ticket->department);
        $this->assertSame('pending', $ticket->ownership['state'] ?? null);
        $this->assertNull($ticket->closure);
    }

    public function test_officer_cannot_reopen_without_reason(): void
    {
        Department::query()->create([
            'external_id' => 'dept-it',
            'name' => 'Information Technology',
            'code' => 'IT',
            'active' => true,
            'status' => 'active',
        ]);

        $officer = User::factory()->create([
            'role' => Roles::RM_OFFICER,
            'role_label' => Roles::label(Roles::RM_OFFICER),
        ]);
        $ticket = $this->closedTicket('RISK-TEST-O003', 'Information Technology');

        $this->actingAs($officer)
            ->post('/officer/tickets/RISK-TEST-O003/reopen', [
                'reason' => '   ',
                'department' => 'Information Technology',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('closed', $ticket->status);
    }

    public function test_guest_cannot_post_officer_thread_comment(): void
    {
        $this->submittedTicket('RISK-TEST-O005');

        $this->post('/officer/tickets/RISK-TEST-O005/thread-comment', [
            'comment' => 'Guest note',
        ])->assertRedirect();

        $ticket = RiskTicket::query()->where('reference', 'RISK-TEST-O005')->first();
        $this->assertSame([], $ticket?->thread_comments ?? []);
    }

    public function test_officer_can_post_thread_comment_and_reply(): void
    {
        $officer = User::factory()->create([
            'role' => Roles::RM_OFFICER,
            'role_label' => Roles::label(Roles::RM_OFFICER),
            'position' => 'Risk Management Officer',
        ]);
        $ticket = $this->submittedTicket('RISK-TEST-O006');

        $this->actingAs($officer)
            ->post('/officer/tickets/RISK-TEST-O006/thread-comment', [
                'comment' => 'Governance note',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $thread = $ticket->thread_comments ?? [];
        $this->assertCount(1, $thread);
        $this->assertSame('Governance note', $thread[0]['body'] ?? null);
        $this->assertSame('governance', $thread[0]['kind'] ?? null);
        $this->assertSame(Roles::RM_OFFICER, $thread[0]['authorRole'] ?? null);

        $this->actingAs($officer)
            ->post('/officer/tickets/RISK-TEST-O006/thread-comment', [
                'comment' => 'Follow-up',
                'parentId' => $thread[0]['id'],
            ])
            ->assertRedirect();

        $ticket->refresh();
        $thread = $ticket->thread_comments ?? [];
        $this->assertCount(2, $thread);
        $this->assertSame($thread[0]['id'], $thread[1]['parentId'] ?? null);
        $this->assertSame('Follow-up', $thread[1]['body'] ?? null);
        $this->assertSame('governance', $thread[1]['kind'] ?? null);
    }

    public function test_officer_empty_thread_comment_is_rejected(): void
    {
        $officer = User::factory()->create([
            'role' => Roles::RM_OFFICER,
            'role_label' => Roles::label(Roles::RM_OFFICER),
        ]);
        $ticket = $this->submittedTicket('RISK-TEST-O007');

        $this->actingAs($officer)
            ->post('/officer/tickets/RISK-TEST-O007/thread-comment', [
                'comment' => '   ',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame([], $ticket->thread_comments ?? []);
    }

    public function test_non_officer_cannot_post_thread_comment(): void
    {
        $head = User::factory()->create([
            'role' => Roles::DEPT_HEAD,
            'role_label' => Roles::label(Roles::DEPT_HEAD),
        ]);
        $this->submittedTicket('RISK-TEST-O008');

        $this->actingAs($head)
            ->post('/officer/tickets/RISK-TEST-O008/thread-comment', [
                'comment' => 'Not my role',
            ])
            ->assertRedirect();

        $ticket = RiskTicket::query()->where('reference', 'RISK-TEST-O008')->first();
        $this->assertSame([], $ticket?->thread_comments ?? []);
    }

    public function test_non_officer_cannot_reopen_ticket(): void
    {
        $head = User::factory()->create([
            'role' => Roles::DEPT_HEAD,
            'role_label' => Roles::label(Roles::DEPT_HEAD),
        ]);
        $this->closedTicket('RISK-TEST-O004', 'Information Technology');

        $this->actingAs($head)
            ->post('/officer/tickets/RISK-TEST-O004/reopen', [
                'reason' => 'Not my role',
                'department' => 'Information Technology',
            ])
            ->assertRedirect();

        $this->assertSame('closed', RiskTicket::query()->where('reference', 'RISK-TEST-O004')->value('status'));
    }

    private function closedTicket(string $ref, string $department): RiskTicket
    {
        return RiskTicket::query()->create([
            'external_id' => 'ext-'.$ref,
            'reference' => $ref,
            'title' => $ref,
            'status' => 'closed',
            'department' => $department,
            'submitted_by' => 'reporter1',
            'deleted' => false,
            'closure' => ['notes' => 'Closed'],
        ]);
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
            'deleted' => false,
        ]);
    }
}
