<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    protected function tearDown(): void
    {
        $this->resetAuthState();
        parent::tearDown();
    }

    protected function resetAuthState(): void
    {
        $this->flushHeaders();
        if ($this->app->bound('auth')) {
            $this->app['auth']->forgetGuards();
        }
    }

    protected function bearerTokenFor(string $username, string $password): string
    {
        $this->resetAuthState();

        $response = $this->postJson('/v1/auth/token', compact('username', 'password'));
        $response->assertOk();

        return (string) $response->json('token');
    }

    protected function withBearerToken(string $token): static
    {
        return $this->withToken($token);
    }

    public function withToken(string $token, string $type = 'Bearer'): static
    {
        $this->resetAuthState();

        return parent::withToken($token, $type);
    }

    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }
}
