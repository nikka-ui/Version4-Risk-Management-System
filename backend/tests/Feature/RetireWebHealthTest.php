<?php

namespace Tests\Feature;

use Tests\TestCase;

class RetireWebHealthTest extends TestCase
{
    public function test_health_reports_phase_nine_slice_seven(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 12)
            ->assertJsonPath('slice', 1);
    }

    public function test_guest_login_is_blade(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign In', false);
    }

    public function test_guest_internal_org_is_unauthorized_json(): void
    {
        $this->postJson('/internal/org/users', ['op' => 'upsert', 'user' => ['username' => 'x']])
            ->assertUnauthorized();
    }
}
