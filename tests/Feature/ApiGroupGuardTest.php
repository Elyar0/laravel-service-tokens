<?php

namespace Elyar\ServiceTokens\Tests\Feature;

use Elyar\ServiceTokens\Http\Middleware\AuthenticateActiveServiceTokensMiddleware;
use Elyar\ServiceTokens\Http\Middleware\AuthenticateServiceTokensMiddleware;
use Elyar\ServiceTokens\Tests\TestCase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

class ApiGroupGuardTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        Route::middleware('api')->get('api/grouped', fn() => 'ok');
    }

    public function test_middleware_aliases_are_registered(): void
    {
        $aliases = $this->app->make(Router::class)->getMiddleware();

        $this->assertSame(AuthenticateServiceTokensMiddleware::class, $aliases['service-tokens'] ?? null);
        $this->assertSame(AuthenticateActiveServiceTokensMiddleware::class, $aliases['service-tokens.active'] ?? null);
    }

    public function test_api_group_is_guarded_by_default(): void
    {
        $this->getJson('api/grouped')
            ->assertUnauthorized()
            ->assertJson(['error_type' => 'authorization_token_not_provided']);
    }

    public function test_guarded_api_group_accepts_a_granted_token(): void
    {
        [, $plain] = $this->makeToken(permissions: ['GET@api/grouped']);

        $this->getJson('api/grouped', ['Authorization' => $plain])->assertOk();
    }
}
