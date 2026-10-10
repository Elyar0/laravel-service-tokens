<?php

namespace Elyar\ServiceTokens\Tests;

use Elyar\ServiceTokens\DTO\GeneratedToken;
use Elyar\ServiceTokens\Http\Middleware\AuthenticateActiveServiceTokensMiddleware;
use Elyar\ServiceTokens\Http\Middleware\AuthenticateServiceTokensMiddleware;
use Elyar\ServiceTokens\Models\ServiceToken;
use Elyar\ServiceTokens\ServiceTokensServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [ServiceTokensServiceProvider::class];
    }

    protected function defineRoutes($router): void
    {
        Route::middleware(AuthenticateServiceTokensMiddleware::class)
            ->get('api/ping', fn() => ['service' => serviceAuth()->id()]);

        Route::middleware(AuthenticateActiveServiceTokensMiddleware::class)
            ->get('api/self', fn() => ['service' => serviceAuth()->id()]);
    }

    /**
     * Create a token row and return it with its plain-text value.
     *
     * @return array{0: ServiceToken, 1: string}
     */
    protected function makeToken(array $attributes = [], array $permissions = ['GET@api/ping']): array
    {
        $generated = GeneratedToken::generate($attributes['name'] ?? 'billing');

        $token = ServiceToken::create(array_merge([
            'name' => $generated->name,
            'token_hash' => $generated->hash,
            'active' => true,
        ], $attributes));

        foreach ($permissions as $permission) {
            [$method, $uri] = explode('@', $permission);
            $token->permissions()->create(['method' => $method, 'uri' => $uri]);
        }

        return [$token, $generated->token];
    }
}
