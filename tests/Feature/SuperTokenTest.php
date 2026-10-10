<?php

namespace Elyar\ServiceTokens\Tests\Feature;

use Elyar\ServiceTokens\Models\ServiceToken;
use Elyar\ServiceTokens\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class SuperTokenTest extends TestCase
{
    public function test_super_token_reaches_endpoints_without_grants(): void
    {
        [$token, $plain] = $this->makeToken(['super' => true], permissions: []);

        $this->getJson('api/ping', ['Authorization' => $plain])
            ->assertOk()
            ->assertJson(['service' => $token->id]);
    }

    public function test_regular_token_still_needs_grants(): void
    {
        [, $plain] = $this->makeToken(permissions: []);

        $this->getJson('api/ping', ['Authorization' => $plain])
            ->assertUnauthorized()
            ->assertJson(['error_type' => 'endpoint_not_allowed']);
    }

    public function test_super_token_is_still_rejected_when_revoked_inactive_or_expired(): void
    {
        [, $revoked] = $this->makeToken(['name' => 'revoked', 'super' => true, 'revoked_at' => now()], permissions: []);
        [, $inactive] = $this->makeToken(['name' => 'inactive', 'super' => true, 'active' => false], permissions: []);
        [, $expired] = $this->makeToken(['name' => 'expired', 'super' => true, 'expires_at' => now()->subMinute()], permissions: []);

        $this->getJson('api/ping', ['Authorization' => $revoked])->assertJson(['error_type' => 'token_revoked']);
        $this->getJson('api/ping', ['Authorization' => $inactive])->assertJson(['error_type' => 'inactive_token']);
        $this->getJson('api/ping', ['Authorization' => $expired])->assertJson(['error_type' => 'token_expired']);
    }

    public function test_tokens_are_not_super_by_default(): void
    {
        [$token] = $this->makeToken();

        $this->assertFalse($token->fresh()->super);
    }

    public function test_my_access_reports_super(): void
    {
        [, $plain] = $this->makeToken(['super' => true], permissions: []);

        $this->getJson('api/service-tokens/me/my-access', ['Authorization' => $plain])
            ->assertOk()
            ->assertJson(['super' => true, 'data' => []]);
    }

    public function test_json_listing_shows_super(): void
    {
        $this->makeToken(['super' => true]);

        Artisan::call('st:manage', ['--json' => true]);

        $this->assertTrue(json_decode(Artisan::output(), true)[0]['super']);
    }

    public function test_creating_a_super_token_skips_the_permission_picker(): void
    {
        $this->artisan('st:manage')
            ->expectsConfirmation('Create one now?', 'yes')
            ->expectsQuestion('Token name', 'admin')
            ->expectsConfirmation('Active?', 'yes')
            ->expectsConfirmation('Super token? It can reach every endpoint without per-endpoint permissions.', 'yes')
            ->expectsQuestion('Expires at (YYYY-MM-DD or natural language like "+30 days"; leave blank for no expiry)', '')
            ->expectsOutputToContain('Host@admin:')
            ->assertSuccessful();

        $token = ServiceToken::where('name', 'admin')->firstOrFail();
        $this->assertTrue($token->super);
        $this->assertSame(0, $token->permissions()->count());
    }
}
