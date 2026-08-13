<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_root_redirects_to_unprefixed_login(): void
    {
        config(['rms.edge_root' => true, 'rms.edge_ui' => true]);

        $this->assertLocationEndsWith($this->get('/'), '/login');
        $location = (string) $this->get('/')->headers->get('Location');
        $this->assertFalse(str_contains($location, '/laravel/'), 'Location: '.$location);
    }

    public function test_authenticated_president_root_redirects_to_unprefixed_console(): void
    {
        config(['rms.edge_root' => true, 'rms.edge_ui' => true]);

        $user = User::factory()->create([
            'role' => Roles::PRESIDENT,
            'role_label' => Roles::label(Roles::PRESIDENT),
        ]);

        $this->assertLocationEndsWith(
            $this->actingAs($user)->get('/'),
            '/president',
        );
    }

    public function test_guest_root_opt_out_redirects_to_express_login(): void
    {
        config(['rms.edge_root' => false, 'rms.edge_ui' => false]);

        $response = $this->get('/');
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertTrue(
            str_ends_with($location, '/login') && ! str_contains($location, '/laravel/'),
            'Location: '.$location,
        );
    }

    public function test_guest_dashboard_redirects_to_login(): void
    {
        config(['rms.edge_ui' => true]);

        $this->assertLocationEndsWith($this->get('/dashboard'), '/login');
    }

    public function test_employee_dashboard_renders_stub(): void
    {
        config(['rms.edge_ui' => true]);

        $user = User::factory()->create([
            'name' => 'Stub Employee',
            'role' => Roles::EMPLOYEE,
            'role_label' => Roles::label(Roles::EMPLOYEE),
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Welcome, Stub Employee')
            ->assertSee('Access assigned risk workflows');
    }

    public function test_admin_dashboard_redirects_to_console(): void
    {
        config(['rms.edge_ui' => true]);

        $user = User::factory()->admin()->create();

        $this->assertLocationEndsWith(
            $this->actingAs($user)->get('/dashboard'),
            '/admin',
        );
    }

    public function test_legacy_prefixed_paths_when_edge_ui_off(): void
    {
        config(['rms.edge_root' => true, 'rms.edge_ui' => false]);

        $this->assertLocationEndsWith($this->get('/'), '/laravel/login');
    }

    private function assertLocationEndsWith(\Illuminate\Testing\TestResponse $response, string $suffix): void
    {
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertTrue(
            str_ends_with($location, $suffix),
            "Expected Location to end with {$suffix}, got {$location}",
        );
    }
}
