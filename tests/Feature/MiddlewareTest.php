<?php

namespace Elyar\ServiceTokens\Tests\Feature;

use Elyar\ServiceTokens\Models\ServiceToken;
use Elyar\ServiceTokens\Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;

class MiddlewareTest extends TestCase
{
    public function test_valid_token_with_permission_passes(): void
    {
        [$token, $plain] = $this->makeToken();

        $this->getJson('api/ping', ['Authorization' => $plain])
            ->assertOk()
            ->assertJson(['service' => $token->id]);

        $this->assertNotNull($token->fresh()->last_used_at);
    }

    public function test_bearer_prefix_is_accepted(): void
    {
        [, $plain] = $this->makeToken();

        $this->getJson('api/ping', ['Authorization' => "bearer  {$plain}"])->assertOk();
    }

    public function test_missing_header_is_rejected(): void
    {
        $this->getJson('api/ping')
            ->assertUnauthorized()
            ->assertJson(['error_type' => 'authorization_token_not_provided']);
    }

    #[DataProvider('malformedHeaders')]
    public function test_malformed_header_is_rejected(string $header): void
    {
        $this->getJson('api/ping', ['Authorization' => $header])
            ->assertUnauthorized()
            ->assertJson(['error_type' => 'malformed_token']);
    }

    public static function malformedHeaders(): array
    {
        return [
            'no separators' => ['garbage'],
            'no secret separator' => ['Host@billing'],
            'empty secret' => ['Host@billing:'],
            'empty name' => ['Host@:abc'],
        ];
    }

    public function test_wrong_secret_is_rejected(): void
    {
        $this->makeToken();

        $this->getJson('api/ping', ['Authorization' => 'Host@billing:' . str_repeat('0', 64)])
            ->assertUnauthorized()
            ->assertJson(['error_type' => 'invalid_token']);
    }

    public function test_revoked_token_reports_revocation_before_permissions(): void
    {
        [, $plain] = $this->makeToken(['revoked_at' => now()], permissions: []);

        $this->getJson('api/ping', ['Authorization' => $plain])
            ->assertUnauthorized()
            ->assertJson(['error_type' => 'token_revoked']);
    }

    public function test_inactive_token_is_rejected(): void
    {
        [, $plain] = $this->makeToken(['active' => false]);

        $this->getJson('api/ping', ['Authorization' => $plain])
            ->assertUnauthorized()
            ->assertJson(['error_type' => 'inactive_token']);
    }

    public function test_expired_token_is_rejected(): void
    {
        [, $plain] = $this->makeToken(['expires_at' => now()->subMinute()]);

        $this->getJson('api/ping', ['Authorization' => $plain])
            ->assertUnauthorized()
            ->assertJson(['error_type' => 'token_expired']);
    }

    public function test_expired_token_is_rejected_by_active_middleware(): void
    {
        [, $plain] = $this->makeToken(['expires_at' => now()->subMinute()]);

        $this->getJson('api/self', ['Authorization' => $plain])
            ->assertUnauthorized()
            ->assertJson(['error_type' => 'token_expired']);
    }

    public function test_future_expiry_passes(): void
    {
        [, $plain] = $this->makeToken(['expires_at' => now()->addDay()]);

        $this->getJson('api/ping', ['Authorization' => $plain])->assertOk();
    }

    public function test_endpoint_without_permission_is_rejected(): void
    {
        [, $plain] = $this->makeToken(permissions: ['GET@api/other']);

        $this->getJson('api/ping', ['Authorization' => $plain])
            ->assertUnauthorized()
            ->assertJson(['error_type' => 'endpoint_not_allowed']);
    }

    public function test_rejected_request_does_not_authenticate_service(): void
    {
        [, $plain] = $this->makeToken(['revoked_at' => now()]);

        $this->getJson('api/self', ['Authorization' => $plain])->assertUnauthorized();

        $this->assertFalse(serviceAuth()->check());
    }

    public function test_app_name_with_separators_still_parses(): void
    {
        config(['app.name' => 'My:App@prod']);
        [, $plain] = $this->makeToken();

        $this->getJson('api/ping', ['Authorization' => $plain])->assertOk();
    }

    public function test_my_access_lists_own_permissions(): void
    {
        [, $plain] = $this->makeToken(permissions: ['GET@api/ping', 'POST@api/orders']);

        $this->getJson('api/service-tokens/me/my-access', ['Authorization' => $plain])
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_secret_is_stored_as_sha256(): void
    {
        [$token, $plain] = $this->makeToken();
        $secret = substr($plain, strrpos($plain, ':') + 1);

        $this->assertSame(hash('sha256', $secret), $token->token_hash);
    }

    public function test_legacy_bcrypt_hash_is_rejected(): void
    {
        $secret = str_repeat('a', 64);
        ServiceToken::create(['name' => 'legacy', 'token_hash' => Hash::make($secret), 'active' => true]);

        $this->getJson('api/self', ['Authorization' => "Host@legacy:{$secret}"])
            ->assertUnauthorized()
            ->assertJson(['error_type' => 'invalid_token']);
    }

    public function test_last_used_at_is_written_at_most_once_per_interval(): void
    {
        $this->travelTo('2030-01-01 00:00:00');
        [$token, $plain] = $this->makeToken();

        $this->getJson('api/ping', ['Authorization' => $plain])->assertOk();
        $this->assertSame('2030-01-01 00:00:00', $token->fresh()->last_used_at->toDateTimeString());

        $this->travel(30)->seconds();
        $this->getJson('api/ping', ['Authorization' => $plain])->assertOk();
        $this->assertSame('2030-01-01 00:00:00', $token->fresh()->last_used_at->toDateTimeString());

        $this->travel(31)->seconds();
        $this->getJson('api/ping', ['Authorization' => $plain])->assertOk();
        $this->assertSame('2030-01-01 00:01:01', $token->fresh()->last_used_at->toDateTimeString());
    }
}
