<?php

namespace Elyar\ServiceTokens\Http\Middleware;

use Elyar\ServiceTokens\Support\ApiResponse;
use Elyar\ServiceTokens\Models\ServiceToken;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accepts a valid, active, unexpired token only if it was granted the
 * requested route as a method@uri permission, or is a super token.
 */
class AuthenticateServiceTokensMiddleware extends AuthenticateActiveServiceTokensMiddleware
{
    protected function authorizeRoute(Request $request, ServiceToken $serviceToken): ?Response
    {
        if ($serviceToken->super) {
            return null;
        }

        $route = $request->route();
        $requestedUri = $route ? ltrim($route->uri(), '/') : $request->path();

        if (!$serviceToken->permissions()->where([
            'method' => $request->method(),
            'uri' => $requestedUri,
        ])->exists()) {
            return ApiResponse::error(
                'Unauthorized',
                401,
                'endpoint_not_allowed');
        }

        return null;
    }
}
