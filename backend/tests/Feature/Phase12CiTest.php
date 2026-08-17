<?php

namespace Tests\Feature;

use Tests\TestCase;

class Phase12CiTest extends TestCase
{
    public function test_health_reports_phase_twelve_slice_one(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 12)
            ->assertJsonPath('slice', 1);
    }
}
