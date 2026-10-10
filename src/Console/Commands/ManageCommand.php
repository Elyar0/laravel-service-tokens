<?php

namespace Elyar\ServiceTokens\Console\Commands;

use Elyar\ServiceTokens\DTO\GeneratedToken;
use Elyar\ServiceTokens\Models\ServiceToken;
use Elyar\ServiceTokens\Support\TokenInput;
use Elyar\ServiceTokens\Traits\TokenPermissionManagement;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use Throwable;
use function Laravel\Prompts\{confirm, info, multisearch, multiselect, note, select, table, text, warning};

class ManageCommand extends Command
{
    use TokenPermissionManagement;

    protected $signature = 'st:manage
        {--json : Output tokens as JSON and exit}
        {--name= : Filter by (partial) name}';

    protected $description = 'List and manage service tokens interactively';

    public function handle(): int
    {
        try {
            while (true) {
                $query = ServiceToken::query()->orderBy('name');

                if ($filter = trim((string)$this->option('name'))) {
                    $query->where('name', 'like', "%{$filter}%");
                }

                $tokens = $query->get();

                if ($this->option('json')) {
                    // Don’t leak secrets; show safe subset
                    $safe = $tokens->map(fn($t) => [
                        'id' => $t->id,
                        'name' => $t->name,
                        'super' => $t->super,
                        'created_at' => optional($t->created_at)->toDateTimeString(),
                        'last_used_at' => optional($t->last_used_at)->toDateTimeString(),
                        'expires_at' => optional($t->expires_at)->toDateTimeString() ?? '♾',
                    ]);
                    $this->line($safe->toJson(JSON_PRETTY_PRINT));
                    break;
                }

                if ($tokens->isEmpty()) {
                    warning('No service tokens found.');
                    if (confirm('Create one now?')) {
                        $created = $this->createTokenFlow();
                        if ($created) {
                            info('Token created.');
                        }
                    }
                    break;
                }

                // Show table
                table(
                    headers: ['Name', 'Perms', 'Super', 'Created', 'Last Used', 'Expires', 'Revoked', 'Active'],
                    rows: $tokens->map(fn($t) => [
                        $t->name,
                        $t->permissions()->count(),
                        $t->super ? 'yes' : 'no',
                        Carbon::make($t->created_at)?->diffForHumans() ?: '—',
                        Carbon::make($t->last_used_at)?->diffForHumans() ?: '—',
                        Carbon::make($t->expires_at)?->diffForHumans() ?: '—',
                        $t->revoked_at ? 'yes' : 'no',
                        $t->active ? 'true' : 'false',
                    ])->all()
                );

                // Choose a token to manage
                $choices = $tokens->mapWithKeys(fn($t) => [$t->id => $t->name])->all();
                $choices = $choices + ['__create__' => '➕ Create new token', '__quit__' => 'Quit'];

                $picked = select(
                    label: 'Select a token',
                    options: $choices,
                    default: array_key_first($choices)
                );

                if ($picked === '__quit__' || $picked === null) {
                    break;
                }

                if ($picked === '__create__') {
                    $created = $this->createTokenFlow();
                    if ($created) info('Token created.');
                    break;
                }

                /** @var ServiceToken $token */
                $token = $tokens->firstWhere('id', $picked);
                if (!$token) {
                    warning('Token not found.');
                    return self::FAILURE;
                }

                $this->manageServiceToken($token);
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }

    protected function createTokenFlow(): ?ServiceToken
    {
        $name = text(
            label: 'Token name',
            placeholder: 'E.g. billing-service',
            required: true,
            validate: fn(string $value) => TokenInput::validateName($value)
        );

        $active = confirm('Active?', default: true);

        $super = confirm('Super token? It can reach every endpoint without per-endpoint permissions.', default: false);

        $expiresInput = text(
            label: 'Expires at (YYYY-MM-DD or natural language like "+30 days"; leave blank for no expiry)',
            required: false,
            validate: fn(string $value) => TokenInput::validateExpiry($value)
        );
        $expiresAt = TokenInput::parseExpiry($expiresInput);

        $token = new ServiceToken();
        $token->name = $name;
        $token->active = $active;
        $token->super = $super;
        $token->expires_at = $expiresAt;

        $generatedToken = $this->generateToken($name);

        $token->token_hash = $generatedToken->hash;
        $token->save();

        if (!$super) {
            $this->addPermissions($token);
        }

        note("Token created. Store this value securely (you won't see it again):\n\n{$generatedToken->token}\n");
        return $token;
    }

    private function generateToken(string $name): GeneratedToken
    {
        return GeneratedToken::generate($name);
    }

    /**
     * Every grantable method@uri pair among the application's api routes.
     *
     * @return string[]
     */
    private function apiEndpoints(): array
    {
        /** @var Router $router */
        $router = app(Router::class);

        return collect($router->getRoutes())
            ->filter(fn($route) => str_starts_with($route->uri(), 'api'))
            ->flatMap(fn($route) => collect($route->methods())
                ->intersect(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'])
                ->map(fn($method) => "{$method}@{$route->uri()}"))
            ->values()
            ->all();
    }

    private function addPermissions(ServiceToken $t): void
    {
        $endpoints = $this->apiEndpoints();

        $currentPermissions = $t->permissions->pluck('name')->toArray();
        $missingPermissions = collect($endpoints)->filter(function ($endpoint) use ($currentPermissions) {
            return !in_array($endpoint, $currentPermissions);
        });

        $allowedPermissions = multisearch(
            "Missing Permissions of '{$t->name}'",
            fn(string $value) => strlen($value) > 0
                ? $missingPermissions->filter(function ($endpoint) use ($value) {
                    return Str::contains(strtolower($endpoint), strtolower($value));
                })->values()->all()
                : $missingPermissions->values()->toArray()
        );

        $this->addTokenPermissions($t, $allowedPermissions);

        info('Abilities updated.');
    }

    private function manageServiceToken(ServiceToken $token): void
    {
        while (true) {
            if(!$token->exists) break;

            $this->line(str_repeat('─', 64));

            if($token->revoked_at){
                warning('This is a revoked token. ⚠️');
            }

            table(
                headers: ['Field', 'Value'],
                rows: !$token->revoked_at ? [
                    ['ID', $token->id],
                    ['Name', $token->name],
                    ['Perms', $token->permissions()->count()],
                    ['Super', $token->super ? 'yes (every endpoint)' : 'no'],
                    ['Last Used', optional($token->last_used_at)->toDateTimeString() ?: '—'],
                    ['Expires', optional($token->expires_at)->toDateTimeString() ?: '—'],
                    ['Created', optional($token->created_at)->toDateTimeString() ?: '—'],
                    ['Active', ($token->active ?? true) ? 'yes' : 'no'],
                ] : [
                    ['ID', $token->id],
                    ['Name', $token->name],
                    ['Last Used', optional($token->last_used_at)->toDateTimeString() ?: '—'],
                    ['Revoked At', optional($token->revoked_at)->toDateTimeString() ?: '—'],
                    ['Created', optional($token->created_at)->toDateTimeString() ?: '—'],
                ]
            );

            $action = select(
                label: "Manage '{$token->name}'",
                options: !$token->revoked_at ?
                    [
                        'toggle' => ($token->active ?? true) ? '🛑 Disable' : '✅ Enable',
                        'rename' => '✏️  Rename',
                        'perms' => '🔑 Manage permissions',
                        'super' => $token->super ? '⭐ Remove super' : '⭐ Make super',
                        'expire' => '⏳ Set/clear expiry',
                        'revoke' => '💔  Revoke',
                        'rotate' => '♻️  Rotate (regenerate value)',
                        'delete' => '🗑  Delete',
                        '__back__' => '⬅️  Back',
                    ] :
                    [
                        'delete' => '🗑  Delete',
                        '__back__' => '⬅️  Back',
                    ]
                ,
            );

            if ($action === '__back__' || $action === null) {
                break;
            }

            match ($action) {
                'rename' => $this->renameToken($token),
                'toggle' => $this->toggleActive($token),
                'super' => $this->toggleSuper($token),
                'perms' => $this->managePermissions($token),
                'expire' => $this->setExpiry($token),
                'rotate' => $this->rotateToken($token),
                'revoke' => $this->revokeToken($token),
                'delete' => $this->deleteToken($token),
                default => null,
            };

            // Refresh the model after changes
            $token->refresh();
        }
    }

    protected function renameToken(ServiceToken $t): void
    {
        $newName = text(
            label: 'New name',
            placeholder: $t->name,
            default: $t->name,
            required: true,
            validate: fn(string $value) => TokenInput::validateName($value, ignoreId: $t->id)
        );

        if ($newName === $t->name) {
            return;
        }

        // The name is part of the token value, so the old value stops working.
        if (!confirm('Renaming invalidates the current token value; a new value will be generated. Continue?')) {
            return;
        }

        $generatedToken = $this->generateToken($newName);

        $t->name = $newName;
        $t->token_hash = $generatedToken->hash;
        $t->save();

        note("Renamed. New token value (copy now):\n\n{$generatedToken->token}\n");
    }

    protected function toggleActive(ServiceToken $t): void
    {
        $t->active = !($t->active ?? true);
        $t->save();
        info(($t->active ? 'Enabled' : 'Disabled') . '.');
    }

    protected function toggleSuper(ServiceToken $t): void
    {
        $question = $t->super
            ? "Remove super from '{$t->name}'? It falls back to its own permissions."
            : "Make '{$t->name}' super? It can reach every endpoint, whatever its permissions.";

        if (!confirm($question, default: false)) {
            return;
        }

        $t->super = !$t->super;
        $t->save();
        info($t->super ? 'Token is now super.' : 'Super removed.');
    }

    private function managePermissions(ServiceToken $t): void
    {
        if ($t->super) {
            note("'{$t->name}' is super; its permissions apply only if super is removed.");
        }

        while (true) {
            $action = select(
                label: "Permissions of '{$t->name}'",
                options: [
                    'list' => '👁 List permissions',
                    'add' => '➕ Add permissions',
                    'remove' => '➖ Remove permissions',
                    'update' => '🔄 Update permissions',
                    '__back__' => '⬅️ Back',
                ],
            );

            if ($action === '__back__') {
                break;
            }

            match ($action) {
                'list' => $this->listPermissions($t),
                'add' => $this->addPermissions($t),
                'remove' => $this->removePermissions($t),
                'update' => $this->updatePermissions($t),
                default => null,
            };

            $t->refresh();
        }
    }

    private function listPermissions(ServiceToken $t): void
    {
        $stale = $t->staleGrants($this->apiEndpoints());
        $currentPermissions = $t->permissions->pluck('name')
            ->map(fn($name) => $stale->contains($name) ? "{$name}  (stale: no matching route)" : $name)
            ->toArray();

        info(
            message: "Current Permissions of '{$t->name}'",
        );
        note(implode("\n", $currentPermissions));

        if ($stale->isNotEmpty()) {
            warning("{$stale->count()} stale permission(s) match no current route; remove them or restore the route.");
        }
    }

    private function removePermissions(ServiceToken $t): void
    {
        $currentPermissions = $t->permissions->pluck('name');

        $allowedPermissions = multisearch(
            "Current Permissions of '{$t->name}'",
            fn(string $value) => strlen($value) > 0
                ? $currentPermissions->filter(function ($endpoint) use ($value) {
                    return Str::contains(strtolower($endpoint), strtolower($value));
                })->values()->all()
                : $currentPermissions->values()->toArray()
        );

        $this->subTokenPermissions($t, $allowedPermissions);

        info('Abilities updated.');

    }

    private function updatePermissions(ServiceToken $t): void
    {
        $endpoints = $this->apiEndpoints();

        $currentPermissions = $t->permissions->pluck('name');

        $allowedPermissions = multiselect(
            "Current Permissions of '{$t->name}'",
            options: $endpoints,
            default: $currentPermissions
        );

        $this->putTokenPermissions($t, $allowedPermissions);

        info('Abilities updated.');
    }

    protected function setExpiry(ServiceToken $t): void
    {
        $input = text(
            label: 'Expires at (YYYY-MM-DD or natural language like "+30 days"; leave blank to clear)',
            validate: fn(string $value) => TokenInput::validateExpiry($value)
        );

        $t->expires_at = TokenInput::parseExpiry($input);
        $t->save();
        info('Expiry updated.');
    }

    protected function rotateToken(ServiceToken $t): void
    {
        if (!confirm('Rotate this token? The old value becomes invalid.')) {
            return;
        }

        $generatedToken = $this->generateToken($t->name);


        $t->token_hash = $generatedToken->hash;
        $t->save();

        note("New token value (copy now):\n\n{$generatedToken->token}\n");
    }

    protected function deleteToken(ServiceToken $t): void
    {
        if (!confirm("Delete '{$t->name}' permanently?")) {
            return;
        }
        $t->delete();
        info('Deleted.');
    }

    private function revokeToken(ServiceToken $token)
    {
        if (!confirm("Revoke '{$token->name}' permanently?")) {
            return;
        }
        $token->revoke();
        info('Revoked.');
    }
}
