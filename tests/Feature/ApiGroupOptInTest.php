<?php

namespace Elyar\ServiceTokens\Tests\Feature;

use Elyar\ServiceTokens\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class ApiGroupOptInTest extends TestCase
{
    // Set before the provider boots, which is when it guards the api group.
    protected function defineEnvironment($app): void
    {
        $app['config']->set('service-tokens.guard_api_group', false);
    }

    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        Route::middleware('api')->get('api/public', fn() => 'ok');
        Route::middleware(['api', 'service-tokens'])->get('api/guarded', fn() => 'ok');
    }

    public function test_api_group_routes_do_not_require_a_token(): void
    {
        $this->getJson('api/public')->assertOk();
    }

    public function test_routes_opt_in_through_the_alias(): void
    {
        $this->getJson('api/guarded')
            ->assertUnauthorized()
            ->assertJson(['error_type' => 'authorization_token_not_provided']);
    }
}
