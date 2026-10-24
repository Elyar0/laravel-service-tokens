# Changelog

## v0.2.0

- Dropped the `elyar/personal-laravel-helpers` dependency. Its v0.0.x line
  this package allowed caps Laravel at 12, which blocked installs on
  Laravel 13. Error and success responses are now built by
  `Support\ApiResponse` and keep the same JSON shape.

## v0.1.0

First public release.

- Named service tokens in the form `{app}@{name}:{secret}`, sent in the
  `Authorization` header (a `Bearer ` prefix is optional). Secrets are stored
  as sha256 hashes.
- Per-route permissions (`GET@api/orders`) checked by the `service-tokens`
  middleware. `service-tokens.active` accepts any valid token.
- Super tokens that can call every guarded route without per-route
  permissions.
- Revocation, enable/disable and expiry, each with its own `error_type`.
- `guard_api_group` config (default `true`) to require tokens on the whole
  `api` middleware group.
- `serviceAuth()` exposes the calling service, scoped to the request.
- `GET /api/service-tokens/me/my-access` lets a service list its own
  permissions.
- `st:manage`, an interactive command to create, rotate, rename, expire,
  revoke and delete tokens and manage their permissions. It flags permissions
  that no longer match any route.
- `last_used_at` tracking, written at most once per `last_used_interval`
  seconds.
