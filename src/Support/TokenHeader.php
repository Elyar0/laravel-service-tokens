<?php

namespace Elyar\ServiceTokens\Support;

class TokenHeader
{
    /**
     * Split an "{app}@{name}:{secret}" Authorization value into its name and
     * secret. An optional "Bearer " prefix is ignored. The app part is only a
     * label and may itself contain ":" or "@", so both splits use the last
     * separator - names and secrets never contain either.
     *
     * @return array{name: string, secret: string}|null null when malformed
     */
    public static function parse(string $header): ?array
    {
        $value = trim(preg_replace('/^bearer\s+/i', '', trim($header)));

        $secretAt = strrpos($value, ':');
        if ($secretAt === false) {
            return null;
        }

        $head = substr($value, 0, $secretAt);
        $secret = substr($value, $secretAt + 1);

        $nameAt = strrpos($head, '@');
        if ($nameAt === false) {
            return null;
        }

        $name = substr($head, $nameAt + 1);

        if ($name === '' || $secret === '') {
            return null;
        }

        return ['name' => $name, 'secret' => $secret];
    }
}
