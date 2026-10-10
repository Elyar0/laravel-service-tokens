<?php

namespace Elyar\ServiceTokens\Services;

use Elyar\ServiceTokens\Models\ServiceToken;

class ServiceAuth
{
    private ?ServiceToken $service = null;

    /**
     * Determine if the current service is authenticated.
     *
     * @return bool
     */
    public function check(): bool
    {
        return !is_null($this->service);
    }

    /**
     * Get the currently authenticated service.
     *
     * @return ServiceToken|null
     */
    public function service(): ?ServiceToken
    {
        return $this->service;
    }

    /**
     * Get the ID for the currently authenticated service.
     *
     * @return int|null
     */
    public function id(): int|null
    {
        return $this->service?->id;
    }

    /**
     * Set the current service.
     *
     * @param ServiceToken $token
     * @return $this
     */
    public function setService(ServiceToken $token): self
    {
        $this->service = $token;
        return $this;
    }
}