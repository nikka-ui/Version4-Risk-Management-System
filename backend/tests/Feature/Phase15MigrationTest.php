<?php

namespace Tests\Feature;

use Tests\TestCase;

class Phase15MigrationTest extends TestCase
{
    public function test_health_reports_phase_sixteen_slice_three(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('phase', 16)
            ->assertJsonPath('slice', 3)
            ->assertJsonPath('migration', 'complete');
    }

    public function test_frontend_workflow_mutations_exist(): void
    {
        $candidates = [
            dirname(base_path()).DIRECTORY_SEPARATOR.'frontend'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'TicketWorkflow.tsx',
            base_path('..'.DIRECTORY_SEPARATOR.'frontend'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'TicketWorkflow.tsx'),
        ];

        $path = null;
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $path = $candidate;
                break;
            }
        }

        if ($path === null) {
            $this->markTestSkipped('frontend TicketWorkflow is not mounted in this test environment.');
        }

        $source = (string) file_get_contents($path);
        $this->assertStringContainsString('Accept ownership', $source);
        $this->assertStringContainsString('president-decision', $source);
        $this->assertStringContainsString('/reopen', $source);
        $this->assertStringContainsString('/comments', $source);
    }

    public function test_frontend_admin_department_page_exists(): void
    {
        $candidates = [
            dirname(base_path()).DIRECTORY_SEPARATOR.'frontend'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'departments'.DIRECTORY_SEPARATOR.'page.tsx',
            base_path('..'.DIRECTORY_SEPARATOR.'frontend'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'departments'.DIRECTORY_SEPARATOR.'page.tsx'),
        ];

        $path = null;
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $path = $candidate;
                break;
            }
        }

        if ($path === null) {
            $this->markTestSkipped('frontend departments page is not mounted in this test environment.');
        }

        $source = (string) file_get_contents($path);
        $this->assertStringContainsString('Create department', $source);
        $this->assertStringContainsString('POST', $source);
    }

    public function test_frontend_admin_users_page_exists(): void
    {
        $candidates = [
            dirname(base_path()).DIRECTORY_SEPARATOR.'frontend'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'users'.DIRECTORY_SEPARATOR.'page.tsx',
            base_path('..'.DIRECTORY_SEPARATOR.'frontend'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'users'.DIRECTORY_SEPARATOR.'page.tsx'),
        ];

        $path = null;
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $path = $candidate;
                break;
            }
        }

        if ($path === null) {
            $this->markTestSkipped('frontend users page is not mounted in this test environment.');
        }

        $source = (string) file_get_contents($path);
        $this->assertStringContainsString('Create user', $source);
        $this->assertStringContainsString('/users', $source);
    }
}
