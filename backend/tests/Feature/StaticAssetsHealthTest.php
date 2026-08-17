<?php

namespace Tests\Feature;

use Tests\TestCase;

class StaticAssetsHealthTest extends TestCase
{
    public function test_health_reports_phase_nine_slice_one(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 12)
            ->assertJsonPath('slice', 1);
    }

    public function test_blade_stylesheet_is_published(): void
    {
        $this->assertFileExists(public_path('css/app.css'));
        $this->assertGreaterThan(1000, filesize(public_path('css/app.css')));
        $this->assertFileExists(public_path('img/favicon.png'));
    }
}
