<?php

namespace Elyar\ServiceTokens\Support;

use Elyar\ServiceTokens\Models\ServiceToken;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Validation and parsing for the values typed into st:manage.
 * Each validate* method returns an error message, or null when valid.
 */
class TokenInput
{
    /** Matches the length of the service_tokens.name column. */
    public const NAME_MAX_LENGTH = 16;

    /** ':' and '@' are token separators, so names never contain them. */
    public const NAME_PATTERN = '/^[A-Za-z0-9._-]+$/';

    public static function validateName(string $name, ?int $ignoreId = null): ?string
    {
        if ($name === '') {
            return 'Name is required.';
        }

        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            return 'Name must be at most ' . self::NAME_MAX_LENGTH . ' characters.';
        }

        if (!preg_match(self::NAME_PATTERN, $name)) {
            return 'Name may only contain letters, digits, ".", "_" and "-".';
        }

        $taken = ServiceToken::where('name', $name)
            ->when($ignoreId, fn($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        return $taken ? 'A token with this name already exists.' : null;
    }

    public static function validateExpiry(string $input): ?string
    {
        if (trim($input) === '') {
            return null;
        }

        $date = self::tryParse($input);

        if (!$date) {
            return 'Could not parse date.';
        }

        return $date->isPast() ? 'Expiry must be in the future.' : null;
    }

    /**
     * Parse an already validated expiry; blank means no expiry.
     */
    public static function parseExpiry(string $input): ?Carbon
    {
        return trim($input) === '' ? null : self::tryParse($input);
    }

    private static function tryParse(string $input): ?Carbon
    {
        $input = trim($input);

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $input)) {
                return Carbon::createFromFormat('Y-m-d', $input)->startOfDay();
            }

            return Carbon::parse($input);
        } catch (Throwable) {
            return null;
        }
    }
}
