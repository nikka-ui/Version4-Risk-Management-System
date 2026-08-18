<?php

namespace Tests\Feature;

use App\Services\StoreJsonTicketMirror;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalTicketMirrorTest extends TestCase
{
    use RefreshDatabase;

    private string $storePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storePath = storage_path('app/testing-internal-tickets.json');
        file_put_contents($this->storePath, json_encode(['riskTickets' => []], JSON_PRETTY_PRINT));
        config([
            'rms.store_json_path' => $this->storePath,
            'rms.internal_service_token' => 'test-internal-token',
            'rms.store_json_ticket_mirror' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->storePath)) {
            unlink($this->storePath);
        }
        parent::tearDown();
    }

    public function test_health_reports_phase_nine_slice_seven(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 16)
            ->assertJsonPath('slice', 3);
    }

    public function test_guest_upsert_without_token_is_unauthorized(): void
    {
        $this->postJson('/internal/tickets/upsert', ['ticket' => ['reference' => 'RISK-1']])
            ->assertUnauthorized();
    }

    public function test_upsert_with_token_writes_store_json(): void
    {
        $this->withHeaders(['X-RMS-Service-Token' => 'test-internal-token'])
            ->postJson('/internal/tickets/upsert', [
                'ticket' => ['reference' => 'RISK-T-1', 'title' => 'Mirrored'],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $store = json_decode((string) file_get_contents($this->storePath), true);
        $this->assertSame('Mirrored', $store['riskTickets'][0]['title'] ?? null);
    }

    public function test_mirror_upsert_round_trip(): void
    {
        $result = app(StoreJsonTicketMirror::class)->upsert([
            'reference' => 'RISK-T-2',
            'title' => 'Direct',
        ]);
        $this->assertSame('Direct', $result['ticket']['title'] ?? null);
    }
}
