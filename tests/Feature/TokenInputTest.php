<?php

namespace Elyar\ServiceTokens\Tests\Feature;

use Elyar\ServiceTokens\Support\TokenInput;
use Elyar\ServiceTokens\Tests\TestCase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;

class TokenInputTest extends TestCase
{
    #[DataProvider('invalidNames')]
    public function test_invalid_names_are_rejected(string $name): void
    {
        $this->assertNotNull(TokenInput::validateName($name));
    }

    public static function invalidNames(): array
    {
        return [
            'empty' => [''],
            'too long' => [str_repeat('a', 17)],
            'colon' => ['a:b'],
            'at sign' => ['a@b'],
            'space' => ['a b'],
        ];
    }

    public function test_valid_name_is_accepted(): void
    {
        $this->assertNull(TokenInput::validateName('billing-svc_2.0'));
    }

    public function test_taken_name_is_rejected_unless_it_is_the_same_token(): void
    {
        [$token] = $this->makeToken(['name' => 'billing']);

        $this->assertNotNull(TokenInput::validateName('billing'));
        $this->assertNull(TokenInput::validateName('billing', ignoreId: $token->id));
    }

    public function test_blank_expiry_means_none(): void
    {
        $this->assertNull(TokenInput::validateExpiry(''));
        $this->assertNull(TokenInput::parseExpiry('  '));
    }

    #[DataProvider('invalidExpiries')]
    public function test_invalid_expiry_is_rejected(string $input): void
    {
        $this->assertNotNull(TokenInput::validateExpiry($input));
    }

    public static function invalidExpiries(): array
    {
        return [
            'garbage' => ['not a date at all'],
            'past date' => ['2020-01-01'],
            'past relative' => ['-1 day'],
        ];
    }

    public function test_future_expiry_is_parsed(): void
    {
        $this->assertNull(TokenInput::validateExpiry('+30 days'));

        $parsed = TokenInput::parseExpiry('2999-01-02');
        $this->assertInstanceOf(Carbon::class, $parsed);
        $this->assertSame('2999-01-02 00:00:00', $parsed->toDateTimeString());
    }
}
