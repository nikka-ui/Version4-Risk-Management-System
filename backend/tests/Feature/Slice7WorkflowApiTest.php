<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Slice7WorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    private function token(string $username, string $password): string
    {
        return $this->postJson('/v1/auth/token', compact('username', 'password'))->json('token');
    }

    public function test_health_slice_seven(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 9)
            ->assertJsonPath('slice', 6);
    }

    public function test_personnel_documents_comment_and_reopen(): void
    {
        Department::query()->create([
            'external_id' => 'dept-it',
            'name' => 'Information Technology',
            'code' => 'IT',
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
            'name' => 'IT Head',
        ]);
        User::factory()->create([
            'username' => 'rmo',
            'password' => 'rmo2026',
            'role' => Roles::RM_OFFICER,
            'name' => 'Risk Officer',
        ]);

        $reporterToken = $this->token('reporter', 'a3c2026');
        $deptToken = $this->token('it-head', 'dept2026');
        $officerToken = $this->token('rmo', 'rmo2026');

        $reference = $this->withToken($reporterToken)
            ->postJson('/v1/tickets', [
                'title' => 'Slice7 workflow',
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
        $this->withToken($deptToken)->postJson("/v1/tickets/{$reference}/accept")->assertOk();

        $this->withToken($deptToken)
            ->postJson("/v1/tickets/{$reference}/personnel", [
                'personName' => 'Alex Tech',
                'personRole' => 'Engineer',
            ])
            ->assertOk()
            ->assertJsonPath('ticket.personnel.0.name', 'Alex Tech');

        $this->withToken($deptToken)
            ->postJson("/v1/tickets/{$reference}/documents", [
                'fileCount' => 2,
                'fileNames' => ['plan.pdf', 'photo.png'],
            ])
            ->assertOk();

        $this->withToken($deptToken)
            ->postJson("/v1/tickets/{$reference}/comments", [
                'comment' => 'Working on mitigation.',
            ])
            ->assertOk()
            ->assertJsonPath('ticket.threadComments.0.body', 'Working on mitigation.');

        $this->withToken($reporterToken)
            ->postJson("/v1/tickets/{$reference}/comments", [
                'comment' => 'Thanks for the update.',
            ])
            ->assertOk();

        RiskTicket::query()->where('reference', $reference)->update([
            'status' => 'closed',
            'closure' => ['closedAt' => now()->toIso8601String()],
        ]);

        $this->withToken($officerToken)
            ->postJson("/v1/tickets/{$reference}/reopen", [
                'reason' => 'Need follow-up verification',
                'department' => 'Information Technology',
            ])
            ->assertOk()
            ->assertJsonPath('ticket.status', 'assigned')
            ->assertJsonPath('ticket.department', 'Information Technology');
    }

    public function test_personnel_requires_name(): void
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
                'title' => 'Personnel validation',
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
        $this->withToken($deptToken)->postJson("/v1/tickets/{$reference}/accept")->assertOk();

        $this->withToken($deptToken)
            ->postJson("/v1/tickets/{$reference}/personnel", [])
            ->assertStatus(422);
    }
}
