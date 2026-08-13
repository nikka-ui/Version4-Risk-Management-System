<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeptTicketMutationTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_phase_seven_slice_thirteen(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 8)
            ->assertJsonPath('slice', 3);
    }

    public function test_guest_cannot_accept_ticket(): void
    {
        $this->assignedTicket('RISK-TEST-D001');

        $this->post('/dept/tickets/RISK-TEST-D001/accept', [
            'comment' => 'Nope',
        ])->assertRedirect();

        $this->assertSame('assigned', RiskTicket::query()->where('reference', 'RISK-TEST-D001')->value('status'));
    }

    public function test_dept_head_can_accept_plan_return_reassign_and_close(): void
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

        $head = User::factory()->create([
            'role' => Roles::DEPT_HEAD,
            'role_label' => Roles::label(Roles::DEPT_HEAD),
            'department' => 'Information Technology',
        ]);

        $ticketA = $this->assignedTicket('RISK-TEST-D002');
        $ticketB = $this->assignedTicket('RISK-TEST-D003');
        $ticketC = $this->assignedTicket('RISK-TEST-D004');

        $this->actingAs($head)
            ->post('/dept/tickets/RISK-TEST-D002/accept', ['comment' => 'Taking it'])
            ->assertRedirect();
        $ticketA->refresh();
        $this->assertSame('in_progress', $ticketA->status);
        $this->assertSame('accepted', $ticketA->ownership['state'] ?? null);

        $this->actingAs($head)
            ->post('/dept/tickets/RISK-TEST-D002/action-plan', [
                'summary' => 'Fix the gap',
                'steps' => "One\nTwo",
            ])
            ->assertRedirect();
        $ticketA->refresh();
        $this->assertSame('Fix the gap', $ticketA->action_plan['summary'] ?? null);

        $this->actingAs($head)
            ->post('/dept/tickets/RISK-TEST-D002/return', ['reason' => 'Needs evidence'])
            ->assertRedirect();
        $ticketA->refresh();
        $this->assertSame('ownership_rejected', $ticketA->status);

        $this->actingAs($head)
            ->post('/dept/tickets/RISK-TEST-D003/reassign', [
                'reason' => 'Finance owns this',
                'targetDepartment' => 'Finance',
            ])
            ->assertRedirect();
        $ticketB->refresh();
        $this->assertSame('Finance', $ticketB->department);
        $this->assertSame('assigned', $ticketB->status);

        $ticketC->status = 'pending_audit';
        $ticketC->accomplishment_external_id = 'acc-test-1';
        $ticketC->save();

        $this->actingAs($head)
            ->post('/dept/tickets/RISK-TEST-D004/close', ['comment' => 'Reviewed'])
            ->assertRedirect();
        $ticketC->refresh();
        $this->assertSame('closed', $ticketC->status);
        $this->assertSame('Reviewed', $ticketC->closure['notes'] ?? null);
    }

    public function test_guest_cannot_post_dept_comment(): void
    {
        $this->assignedTicket('RISK-TEST-D006');

        $this->post('/dept/tickets/RISK-TEST-D006/comment', [
            'comment' => 'Guest note',
        ])->assertRedirect();

        $ticket = RiskTicket::query()->where('reference', 'RISK-TEST-D006')->first();
        $this->assertSame([], $ticket?->thread_comments ?? []);
    }

    public function test_dept_head_can_post_comment_and_reply(): void
    {
        $head = User::factory()->create([
            'role' => Roles::DEPT_HEAD,
            'role_label' => Roles::label(Roles::DEPT_HEAD),
            'department' => 'Information Technology',
        ]);
        $ticket = $this->assignedTicket('RISK-TEST-D007');

        $this->actingAs($head)
            ->post('/dept/tickets/RISK-TEST-D007/comment', [
                'comment' => 'Dept note',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $thread = $ticket->thread_comments ?? [];
        $this->assertCount(1, $thread);
        $this->assertSame('Dept note', $thread[0]['body'] ?? null);
        $this->assertSame(Roles::DEPT_HEAD, $thread[0]['authorRole'] ?? null);

        $this->actingAs($head)
            ->post('/dept/tickets/RISK-TEST-D007/comment', [
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

    public function test_dept_head_empty_comment_is_rejected(): void
    {
        $head = User::factory()->create([
            'role' => Roles::DEPT_HEAD,
            'role_label' => Roles::label(Roles::DEPT_HEAD),
            'department' => 'Information Technology',
        ]);
        $ticket = $this->assignedTicket('RISK-TEST-D008');

        $this->actingAs($head)
            ->post('/dept/tickets/RISK-TEST-D008/comment', [
                'comment' => '   ',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame([], $ticket->thread_comments ?? []);
    }

    public function test_other_department_cannot_post_comment(): void
    {
        $head = User::factory()->create([
            'role' => Roles::DEPT_HEAD,
            'role_label' => Roles::label(Roles::DEPT_HEAD),
            'department' => 'Finance',
        ]);
        $this->assignedTicket('RISK-TEST-D009');

        $this->actingAs($head)
            ->post('/dept/tickets/RISK-TEST-D009/comment', [
                'comment' => 'Wrong dept',
            ])
            ->assertRedirect();

        $ticket = RiskTicket::query()->where('reference', 'RISK-TEST-D009')->first();
        $this->assertSame([], $ticket?->thread_comments ?? []);
    }

    public function test_non_dept_head_cannot_accept_ticket(): void
    {
        $reporter = User::factory()->create([
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
            'department' => 'Information Technology',
        ]);
        $this->assignedTicket('RISK-TEST-D005');

        $this->actingAs($reporter)
            ->post('/dept/tickets/RISK-TEST-D005/accept')
            ->assertRedirect();

        $this->assertSame('assigned', RiskTicket::query()->where('reference', 'RISK-TEST-D005')->value('status'));
    }

    private function assignedTicket(string $ref): RiskTicket
    {
        return RiskTicket::query()->create([
            'external_id' => 'ext-'.$ref,
            'reference' => $ref,
            'title' => $ref,
            'status' => 'assigned',
            'department' => 'Information Technology',
            'submitted_by' => 'reporter1',
            'deleted' => false,
            'ownership' => [
                'state' => 'pending',
                'ownerDepartment' => 'Information Technology',
            ],
        ]);
    }
}
