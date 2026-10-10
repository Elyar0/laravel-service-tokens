<?php

namespace Elyar\ServiceTokens\Http\Middleware;

use Elyar\ServiceTokens\Models\ServiceToken;
use Elyar\ServiceTokens\Support\TokenHeader;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accepts any valid, active, unexpired token, regardless of its endpoint
 * permissions - for endpoints where a service inspects itself.
 */
class AuthenticateActiveServiceTokensMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authData = $request->header('Authorization');

        if (!$authData) {
            return apiResponse()->error(
                'Authorization token is required',
                401,
                'authorization_token_not_provided');
        }

        $parsed = TokenHeader::parse($authData);

        if (!$parsed) {
            return apiResponse()->error(
                'Unauthorized',
                401,
                'malformed_token');
        }

        $serviceToken = ServiceToken::where('name', $parsed['name'])->first();

        if (!$serviceToken || !$serviceToken->matchesSecret($parsed['secret'])) {
            return apiResponse()->error(
                'Unauthorized',
                401,
                'invalid_token');
        }

        if ($serviceToken->revoked_at) {
            return apiResponse()->error(
                'Unauthorized',
                401,
                'token_revoked');
        }

        if (!$serviceToken->active) {
            return apiResponse()->error(
                'Unauthorized',
                401,
                'inactive_token');
        }

        if ($serviceToken->isExpired()) {
            return apiResponse()->error(
                'Unauthorized',
                401,
                'token_expired');
        }

        if ($denied = $this->authorizeRoute($request, $serviceToken)) {
            return $denied;
        }

        serviceAuth()->setService($serviceToken);

        $this->touchLastUsed($serviceToken);

        return $next($request);
    }

    private function touchLastUsed(ServiceToken $serviceToken): void
    {
        $interval = (int) config('service-tokens.last_used_interval', 60);

        if ($serviceToken->last_used_at?->gt(now()->subSeconds($interval))) {
            return;
        }

        $serviceToken->last_used_at = now();
        $serviceToken->save();
    }

    /**
     * Extra per-route check for a token that is otherwise valid.
     * Returns the rejection response, or null to let the request through.
     */
    protected function authorizeRoute(Request $request, ServiceToken $serviceToken): ?Response
    {
        return null;
    }
}
