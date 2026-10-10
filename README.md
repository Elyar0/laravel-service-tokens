# Service Tokens

Token authentication for service-to-service calls between Laravel apps.

Each calling service gets a named token. A token can only call the routes it
has been granted (`GET@api/orders`), unless it is a **super** token, which can
call every guarded route. You create and manage tokens with an interactive
artisan command, `st:manage`.

- Laravel 12 or 13, PHP 8.2+
- Secrets are stored as sha256 hashes. The plain value is shown once, when the
  token is created or rotated.
- Tokens can be expired, revoked or disabled, and record when they were last
  used.

## Installation

```bash
composer require elyar/laravel-service-tokens
php artisan migrate
php artisan st:install   # optional: publishes config/service-tokens.php
```

The service provider is auto-discovered. It registers the middleware, the
migrations, the `st:*` commands and the package's own route.

## Protecting routes

By default every route in the host's `api` middleware group requires a valid
token with a permission for that route. This suits apps that only serve other
services.

If the app also has routes for users or the public, turn that off and choose
routes explicitly:

```dotenv
SERVICE_TOKENS_GUARD_API_GROUP=false
```

```php
// Token plus a permission for this method@uri (or a super token)
Route::middleware('service-tokens')->group(function () {
    Route::get('orders', [OrderController::class, 'index']);
});

// Any valid token; permissions are not checked
Route::middleware('service-tokens.active')->get('ping', fn () => 'pong');
```

Inside a guarded route, `serviceAuth()` tells you which service is calling:

```php
serviceAuth()->check();    // bool
serviceAuth()->service();  // Elyar\ServiceTokens\Models\ServiceToken|null
serviceAuth()->id();       // int|null
```

## Calling a protected service

Send the token in the `Authorization` header. A `Bearer ` prefix is optional.

```http
GET /api/orders HTTP/1.1
Authorization: MailService@billing:3f9c...e1
```

The token format is `{app name}@{token name}:{secret}`. The app-name prefix
records which app issued the token; it is not checked.

A service can ask which routes it may call:

```http
GET /api/service-tokens/me/my-access
```

```json
{
    "success": true,
    "message": null,
    "data": [{ "method": "GET", "uri": "api/orders", "name": "GET@api/orders", "...": "..." }],
    "super": false
}
```

## Managing tokens

```bash
php artisan st:manage                 # interactive
php artisan st:manage --name=bill     # only tokens whose name contains "bill"
php artisan st:manage --json          # list as JSON (never includes secrets)
```

From the interactive menu you can:

- **Create** a token. Names are unique, at most 16 characters, and may contain
  letters, digits, `.`, `_` and `-`. The expiry is optional and must be in the
  future, e.g. `2027-01-01` or `+30 days`.
- **Manage permissions**: list, add, remove or replace grants. The choices come
  from the app's current `api*` routes. Grants that no longer match any route
  (for example after a route is renamed) are marked as stale.
- **Make super / Remove super**.
- **Enable or disable** a token, or set or clear its expiry.
- **Rotate**: generate a new secret. The old value stops working immediately.
- **Rename**: this also generates a new value, because the name is part of the
  token.
- **Revoke** (permanent) or **Delete**.

### Super tokens

A super token can call every route guarded by `service-tokens` without any
permissions. It is still rejected when it is revoked, disabled or expired. Give
it only to trusted internal callers.

## Errors

Every rejection returns HTTP 401 with an `error_type`:

| `error_type` | Cause |
|---|---|
| `authorization_token_not_provided` | No `Authorization` header |
| `malformed_token` | Header is not `{app}@{name}:{secret}` |
| `invalid_token` | Unknown token name or wrong secret |
| `token_revoked` | Token was revoked |
| `inactive_token` | Token is disabled |
| `token_expired` | `expires_at` has passed |
| `endpoint_not_allowed` | Token has no permission for this route (`service-tokens` only) |

The checks run in the order listed, so a revoked token always gets
`token_revoked`, whatever its permissions.

## Configuration

`config/service-tokens.php`:

| Key | Default | Purpose |
|---|---|---|
| `guard_api_group` | `true` (`SERVICE_TOKENS_GUARD_API_GROUP`) | Require tokens on the whole `api` group |
| `last_used_interval` | `60` | Minimum seconds between `last_used_at` writes for a token |

## Testing

```bash
composer install
composer test
```

The tests use Orchestra Testbench with an in-memory SQLite database.

## License

MIT. See [LICENSE](LICENSE).
