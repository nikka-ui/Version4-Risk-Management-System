<?php

namespace Tests\Feature;

use Tests\TestCase;

class EdgeFallbackHealthTest extends TestCase
{
    public function test_health_reports_phase_nine_slice_four(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 16)
            ->assertJsonPath('slice', 3);
    }

    public function test_favicon_is_published(): void
    {
        $this->get('/favicon.ico')->assertOk();
    }

    public function test_unknown_path_is_not_found(): void
    {
        $this->get('/rms-edge-fallback-probe')->assertNotFound();
    }
}
