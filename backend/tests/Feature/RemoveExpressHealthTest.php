<?php

namespace Tests\Feature;

use Tests\TestCase;

class RemoveExpressHealthTest extends TestCase
{
    public function test_health_reports_phase_nine_slice_eight(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 16)
            ->assertJsonPath('slice', 3);
    }

    public function test_guest_login_is_blade(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign In', false);
    }
}
