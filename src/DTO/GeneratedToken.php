<?php

namespace Elyar\ServiceTokens\DTO;

use Elyar\ServiceTokens\Models\ServiceToken;

class GeneratedToken
{
    public string $token;

    public function __construct(
        public string $name,
        public string $raw,
        public string $hash,
    )
    {
        $this->token = config('app.name') . '@' . $name . ':' . $raw;
    }

    /**
     * Generate a fresh random secret for the named token.
     */
    public static function generate(string $name): self
    {
        $raw = bin2hex(random_bytes(32));

        return new self($name, $raw, ServiceToken::hashSecret($raw));
    }
}
