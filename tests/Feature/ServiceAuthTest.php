<?php

namespace Elyar\ServiceTokens\Tests\Feature;

use Elyar\ServiceTokens\Tests\TestCase;

class ServiceAuthTest extends TestCase
{
    public function test_authenticated_service_does_not_outlive_its_scope(): void
    {
        [$token] = $this->makeToken();
        serviceAuth()->setService($token);

        $this->app->forgetScopedInstances();

        $this->assertFalse(serviceAuth()->check());
    }
}
