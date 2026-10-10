<?php

namespace Elyar\ServiceTokens\Tests\Feature;

use Elyar\ServiceTokens\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class ManageCommandTest extends TestCase
{
    public function test_json_listing_shows_usage_and_expiry_dates(): void
    {
        $this->travelTo('2030-05-06 07:08:09');
        $this->makeToken([
            'last_used_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        $this->assertSame(0, Artisan::call('st:manage', ['--json' => true]));

        $listed = json_decode(Artisan::output(), true)[0];
        $this->assertSame('2030-05-06 07:08:09', $listed['last_used_at']);
        $this->assertSame('2030-05-07 07:08:09', $listed['expires_at']);
    }

    public function test_rename_issues_a_new_token_value(): void
    {
        [$token, $oldPlain] = $this->makeToken(['name' => 'billing']);

        $this->artisan('st:manage')
            ->expectsChoice('Select a token', $token->id, [$token->id => 'billing', '__create__' => '➕ Create new token', '__quit__' => 'Quit'])
            ->expectsChoice("Manage 'billing'", 'rename', [
                'toggle' => '🛑 Disable', 'rename' => '✏️  Rename', 'perms' => '🔑 Manage permissions',
                'super' => '⭐ Make super',
                'expire' => '⏳ Set/clear expiry', 'revoke' => '💔  Revoke', 'rotate' => '♻️  Rotate (regenerate value)',
                'delete' => '🗑  Delete', '__back__' => '⬅️  Back',
            ])
            ->expectsQuestion('New name', 'invoices')
            ->expectsConfirmation('Renaming invalidates the current token value; a new value will be generated. Continue?', 'yes')
            ->expectsOutputToContain('Host@invoices:')
            ->expectsChoice("Manage 'invoices'", '__back__', [
                'toggle' => '🛑 Disable', 'rename' => '✏️  Rename', 'perms' => '🔑 Manage permissions',
                'super' => '⭐ Make super',
                'expire' => '⏳ Set/clear expiry', 'revoke' => '💔  Revoke', 'rotate' => '♻️  Rotate (regenerate value)',
                'delete' => '🗑  Delete', '__back__' => '⬅️  Back',
            ])
            ->expectsChoice('Select a token', '__quit__', [$token->id => 'invoices', '__create__' => '➕ Create new token', '__quit__' => 'Quit'])
            ->assertSuccessful();

        $this->assertSame('invoices', $token->fresh()->name);
        $this->getJson('api/ping', ['Authorization' => $oldPlain])->assertUnauthorized();
    }
}
