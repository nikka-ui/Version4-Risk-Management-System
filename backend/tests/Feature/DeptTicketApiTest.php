<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeptTicketApiTest extends TestCase
{
    use RefreshDatabase;

    private function token(string $username, string $password): string
    {
        return $this->postJson('/v1/auth/token', compact('username', 'password'))->json('token');
    }

    public function test_health_slice_six(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 5)
            ->assertJsonPath('slice', 14);
    }

    public function test_accept_reject_and_action_plan_flow(): void
    {
        User::factory()->create([
            'username' => 'reporter',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
            'department' => 'Information Technology',
        ]);
        User::factory()->create([
            'username' => 'it-head',
            'password' => 'dept2026',
            'role' => Roles::DEPT_HEAD,
            'department' => 'Information Technology',
            'name' => 'IT Head',
        ]);

        $reporterToken = $this->token('reporter', 'a3c2026');
        $deptToken = $this->token('it-head', 'dept2026');

        $reference = $this->withToken($reporterToken)
            ->postJson('/v1/tickets', [
                'title' => 'IT outage risk',
                'what' => 'Switch failed',
                'why' => 'No redundancy',
                'where' => 'DC',
                'when' => 'Today',
                'who' => 'IT',
                'how' => 'SPOF',
                'evidenceCount' => 1,
            ])
            ->assertCreated()
            ->json('ticket.reference');

        $this->withToken($reporterToken)
            ->postJson("/v1/tickets/{$reference}/submit")
            ->assertOk()
            ->assertJsonPath('ticket.status', 'assigned');

        $this->withToken($reporterToken)
            ->postJson("/v1/tickets/{$reference}/accept")
            ->assertForbidden();

        $this->withToken($deptToken)
            ->postJson("/v1/tickets/{$reference}/accept", ['comment' => 'Taking ownership'])
            ->assertOk()
            ->assertJsonPath('ticket.status', 'in_progress')
            ->assertJsonPath('ticket.ownership.state', 'accepted');

        $this->withToken($deptToken)
            ->putJson("/v1/tickets/{$reference}/action-plan", [
                'summary' => 'Replace core switch',
                'steps' => "Order hardware\nInstall and test",
                'targetDate' => '2026-09-01',
                'submitForReview' => true,
            ])
            ->assertOk();

        $status = $this->withToken($deptToken)
            ->getJson("/v1/tickets/{$reference}")
            ->json('ticket.status');

        $this->assertContains($status, ['in_mitigation', 'pending_president']);
    }

    public function test_reject_requires_reason(): void
    {
        User::factory()->create([
            'username' => 'reporter',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
            'department' => 'Finance',
        ]);
        User::factory()->create([
            'username' => 'fin-head',
            'password' => 'dept2026',
            'role' => Roles::DEPT_HEAD,
            'department' => 'Finance',
        ]);

        $reporterToken = $this->token('reporter', 'a3c2026');
        $deptToken = $this->token('fin-head', 'dept2026');

        $reference = $this->withToken($reporterToken)
            ->postJson('/v1/tickets', [
                'title' => 'Budget process gap',
                'what' => 'Missing controls',
                'why' => 'Process failure',
                'where' => 'Finance',
                'when' => 'Q3',
                'who' => 'Staff',
                'how' => 'Manual workflow',
                'evidenceCount' => 1,
            ])
            ->json('ticket.reference');

        $this->withToken($reporterToken)->postJson("/v1/tickets/{$reference}/submit")->assertOk();

        $this->withToken($deptToken)
            ->postJson("/v1/tickets/{$reference}/reject", [])
            ->assertStatus(422);

        $this->withToken($deptToken)
            ->postJson("/v1/tickets/{$reference}/reject", ['reason' => 'Wrong department routing'])
            ->assertOk()
            ->assertJsonPath('ticket.status', 'ownership_rejected');
    }

    public function test_return_reassign_and_close_flow(): void
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

        User::factory()->create([
            'username' => 'reporter',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
            'department' => 'Information Technology',
        ]);
        User::factory()->create([
            'username' => 'it-head',
            'password' => 'dept2026',
            'role' => Roles::DEPT_HEAD,
            'department' => 'Information Technology',
        ]);
        User::factory()->create([
            'username' => 'fin-head',
            'password' => 'dept2026',
            'role' => Roles::DEPT_HEAD,
            'department' => 'Finance',
        ]);

        $reporterToken = $this->token('reporter', 'a3c2026');
        $itToken = $this->token('it-head', 'dept2026');
        $finToken = $this->token('fin-head', 'dept2026');

        $reference = $this->withToken($reporterToken)
            ->postJson('/v1/tickets', [
                'title' => 'Slice6 workflow',
                'what' => 'Issue',
                'why' => 'Cause',
                'where' => 'HQ',
                'when' => 'Today',
                'who' => 'Team',
                'how' => 'Process',
                'evidenceCount' => 1,
            ])
            ->json('ticket.reference');

        $this->withToken($reporterToken)->postJson("/v1/tickets/{$reference}/submit")->assertOk();

        $this->withToken($itToken)
            ->postJson("/v1/tickets/{$reference}/accept")
            ->assertOk();

        $this->withToken($itToken)
            ->postJson("/v1/tickets/{$reference}/return", [])
            ->assertStatus(422);

        $this->withToken($itToken)
            ->postJson("/v1/tickets/{$reference}/return", ['reason' => 'Needs more evidence'])
            ->assertOk()
            ->assertJsonPath('ticket.status', 'ownership_rejected');

        $this->withToken($reporterToken)->postJson("/v1/tickets/{$reference}/submit")->assertOk();

        $this->withToken($itToken)
            ->postJson("/v1/tickets/{$reference}/reassign", [
                'reason' => 'Finance owns this process',
                'targetDepartment' => 'Finance',
            ])
            ->assertOk()
            ->assertJsonPath('ticket.status', 'assigned')
            ->assertJsonPath('ticket.department', 'Finance');

        $this->withToken($finToken)
            ->postJson("/v1/tickets/{$reference}/accept")
            ->assertOk();

        $this->withToken($finToken)
            ->postJson("/v1/tickets/{$reference}/close", ['closingNotes' => 'Done'])
            ->assertStatus(422);

        $this->withToken($finToken)
            ->patchJson("/v1/tickets/{$reference}", ['title' => 'ignored'])
            ->assertStatus(404);

        \App\Models\RiskTicket::query()->where('reference', $reference)->update([
            'status' => 'pending_audit',
            'accomplishment_external_id' => 'acc-smoke-1',
        ]);

        $this->withToken($finToken)
            ->postJson("/v1/tickets/{$reference}/close", ['closingNotes' => 'Reviewed accomplishment'])
            ->assertOk()
            ->assertJsonPath('ticket.status', 'closed')
            ->assertJsonPath('ticket.closure.closedByRole', 'dept_head');
    }
}
