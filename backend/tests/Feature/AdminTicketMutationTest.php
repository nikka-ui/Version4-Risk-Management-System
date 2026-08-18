<?php

namespace Tests\Feature;

use App\Models\RiskTicket;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTicketMutationTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_reports_phase_seven_slice_thirteen(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 16)
            ->assertJsonPath('slice', 3);
    }

    public function test_guest_cannot_soft_delete_ticket(): void
    {
        $ticket = RiskTicket::query()->create([
            'external_id' => 'ext-guest-del',
            'reference' => 'RISK-TEST-0001',
            'title' => 'Guest delete blocked',
            'status' => 'assigned',
            'deleted' => false,
        ]);

        $this->post('/admin/tickets/RISK-TEST-0001/delete', [
            'reason' => 'Should not work',
        ])->assertRedirect();

        $ticket->refresh();
        $this->assertFalse($ticket->deleted);
    }

    public function test_admin_can_soft_delete_ticket(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = RiskTicket::query()->create([
            'external_id' => 'ext-admin-del',
            'reference' => 'RISK-TEST-0002',
            'title' => 'Admin delete target',
            'status' => 'assigned',
            'deleted' => false,
        ]);

        $this->actingAs($admin)
            ->post('/admin/tickets/RISK-TEST-0002/delete', [
                'reason' => 'Duplicate report',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertTrue($ticket->deleted);
        $this->assertSame('Duplicate report', $ticket->deletion_reason);
        $this->assertSame($admin->username, $ticket->deleted_by);
        $this->assertNotNull($ticket->deleted_at);
    }

    public function test_admin_cannot_soft_delete_without_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = RiskTicket::query()->create([
            'external_id' => 'ext-admin-noreason',
            'reference' => 'RISK-TEST-0003',
            'title' => 'Needs reason',
            'status' => 'assigned',
            'deleted' => false,
        ]);

        $this->actingAs($admin)
            ->post('/admin/tickets/RISK-TEST-0003/delete', [
                'reason' => '   ',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertFalse($ticket->deleted);
    }

    public function test_non_admin_cannot_soft_delete_ticket(): void
    {
        $reporter = User::factory()->create([
            'role' => Roles::SUPERVISOR,
            'role_label' => Roles::label(Roles::SUPERVISOR),
        ]);
        $ticket = RiskTicket::query()->create([
            'external_id' => 'ext-sup-del',
            'reference' => 'RISK-TEST-0004',
            'title' => 'Supervisor blocked',
            'status' => 'assigned',
            'deleted' => false,
        ]);

        $this->actingAs($reporter)
            ->post('/admin/tickets/RISK-TEST-0004/delete', [
                'reason' => 'Nope',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertFalse($ticket->deleted);
    }
}
