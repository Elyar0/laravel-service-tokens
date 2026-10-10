<?php

namespace Elyar\ServiceTokens\Traits;

use Elyar\ServiceTokens\Models\ServiceToken;

trait TokenPermissionManagement
{
    private function addTokenPermissions(ServiceToken $serviceToken, ?array $permissions): void
    {
        if (!$permissions) return;
        collect($permissions)->each(function ($endpoint) use (&$processedPermissions) {
            $parts = explode('@', $endpoint);
            $method = $parts[0] ?? null;
            $uri = $parts[1] ?? null;
            $processedPermissions[] = [
                'method' => $method,
                'uri' => $uri,
            ];
        });
        $serviceToken->permissions()->createMany($processedPermissions);
    }

    private function subTokenPermissions(ServiceToken $serviceToken, ?array $permissions): void
    {
        if (!$permissions) return;
        $idsToDelete = $serviceToken->permissions->whereIn('name', $permissions)->pluck('id')->all();

        if (!empty($idsToDelete)) {
            $serviceToken->permissions()->whereIn('id', $idsToDelete)->delete();
        }
    }

    private function putTokenPermissions(ServiceToken $serviceToken, ?array $permissions): void
    {
        $serviceToken->permissions()->delete();
        if (!$permissions) return;
        collect($permissions)->each(function ($endpoint) use (&$processedPermissions) {
            $parts = explode('@', $endpoint);
            $method = $parts[0];
            $uri = $parts[1];
            $processedPermissions[] = [
                'method' => $method,
                'uri' => $uri,
            ];
        });
        $serviceToken->permissions()->createMany($processedPermissions);
    }

}