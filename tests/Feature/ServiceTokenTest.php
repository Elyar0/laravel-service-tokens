<?php

namespace Elyar\ServiceTokens\Tests\Feature;

use Elyar\ServiceTokens\Tests\TestCase;
use Illuminate\Support\Carbon;

class ServiceTokenTest extends TestCase
{
    public function test_timestamps_and_flags_are_cast(): void
    {
        [$token] = $this->makeToken([
            'expires_at' => now()->addDay(),
            'last_used_at' => now(),
            'revoked_at' => now(),
        ]);
        $token = $token->fresh();

        $this->assertInstanceOf(Carbon::class, $token->expires_at);
        $this->assertInstanceOf(Carbon::class, $token->last_used_at);
        $this->assertInstanceOf(Carbon::class, $token->revoked_at);
        $this->assertIsBool($token->active);
    }

    public function test_revoke_sets_revoked_at(): void
    {
        [$token] = $this->makeToken();

        $token->revoke();

        $this->assertInstanceOf(Carbon::class, $token->fresh()->revoked_at);
    }

    public function test_stale_grants_are_those_without_a_matching_route(): void
    {
        [$token] = $this->makeToken(permissions: ['GET@api/ping', 'POST@api/gone']);

        $this->assertSame(['POST@api/gone'], $token->staleGrants(['GET@api/ping', 'GET@api/self'])->all());
    }
}
