<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmitTicketApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function draftPayload(): array
    {
        return [
            'title' => 'IT network failure risk',
            'what' => 'Core switch failed',
            'why' => 'No redundancy',
            'where' => 'Data center',
            'when' => 'Morning',
            'who' => 'IT staff',
            'how' => 'Single point of failure',
            'location' => 'HQ',
            'evidenceCount' => 1,
        ];
    }

    public function test_submit_moves_draft_to_assigned(): void
    {
        User::factory()->create([
            'username' => 'reporter',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
            'name' => 'Reporter',
            'department' => 'Information Technology',
        ]);

        $token = $this->postJson('/v1/auth/token', [
            'username' => 'reporter',
            'password' => 'a3c2026',
        ])->json('token');

        $reference = $this->withToken($token)
            ->postJson('/v1/tickets', $this->draftPayload())
            ->assertCreated()
            ->json('ticket.reference');

        $this->withToken($token)
            ->postJson('/v1/tickets/'.$reference.'/submit')
            ->assertOk()
            ->assertJsonPath('ticket.status', 'assigned')
            ->assertJsonPath('ticket.ownership.state', 'pending')
            ->assertJsonPath('ticket.submittedBy', 'reporter');

        $this->assertNotEmpty(
            $this->withToken($token)->getJson('/v1/tickets/'.$reference)->json('ticket.department')
        );
    }

    public function test_cannot_submit_non_draft_twice(): void
    {
        User::factory()->create([
            'username' => 'reporter',
            'password' => 'a3c2026',
            'role' => Roles::SUPERVISOR,
        ]);

        $token = $this->postJson('/v1/auth/token', [
            'username' => 'reporter',
            'password' => 'a3c2026',
        ])->json('token');

        $reference = $this->withToken($token)
            ->postJson('/v1/tickets', $this->draftPayload())
            ->json('ticket.reference');

        $this->withToken($token)->postJson('/v1/tickets/'.$reference.'/submit')->assertOk();
        $this->withToken($token)->postJson('/v1/tickets/'.$reference.'/submit')->assertStatus(422);
    }

    public function test_health_slice_four(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 9)
            ->assertJsonPath('slice', 6);
    }
}
