<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Phase 6 slice 2: `/` redirects guests to unprefixed Blade login.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertTrue(
            str_ends_with($location, '/login') && ! str_contains($location, '/laravel/'),
            'Location: '.$location,
        );
    }
}
