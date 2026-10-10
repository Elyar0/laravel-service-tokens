<?php

namespace Elyar\ServiceTokens;

use Elyar\ServiceTokens\Console\Commands\InstallCommand;
use Elyar\ServiceTokens\Console\Commands\ManageCommand;
use Elyar\ServiceTokens\Http\Middleware\AuthenticateActiveServiceTokensMiddleware;
use Elyar\ServiceTokens\Http\Middleware\AuthenticateServiceTokensMiddleware;
use Elyar\ServiceTokens\Services\ServiceAuth;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ServiceTokensServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/service-tokens.php', 'service-tokens');

        $this->initializeSingletons();
    }

    public function boot(): void
    {
        $this->registerCommands();
        $this->registerMiddleware();

        Route::prefix('api/service-tokens')
            ->group(__DIR__ . '/../routes/service-tokens-api.php');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->publishes([
            __DIR__ . '/../config/service-tokens.php' => config_path('service-tokens.php'),
        ], 'service-tokens-config');
    }

    /**
     * Register the package's commands.
     * @return void
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ManageCommand::class,

                InstallCommand::class,
            ]);
        }
    }

    /**
     * Alias the token middleware and, unless disabled, guard the api group.
     * @return void
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('service-tokens', AuthenticateServiceTokensMiddleware::class);
        $router->aliasMiddleware('service-tokens.active', AuthenticateActiveServiceTokensMiddleware::class);

        if (!config('service-tokens.guard_api_group')) {
            return;
        }

        // Through the kernel, so the group survives its sync to the router.
        $kernel = $this->app->make(Kernel::class);
        if (array_key_exists('api', $kernel->getMiddlewareGroups())) {
            $kernel->prependMiddlewareToGroup('api', 'service-tokens');
        }
    }

    private function initializeSingletons(): void
    {
        $this->app->scoped(ServiceAuth::class, fn() => new ServiceAuth());
    }
}
