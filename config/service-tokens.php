<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Guard the api middleware group
    |--------------------------------------------------------------------------
    |
    | When true, token auth with per-route permissions is prepended to the
    | whole `api` middleware group - the right default for service-only apps.
    | Set it to false to opt routes in individually with the
    | `service-tokens` or `service-tokens.active` middleware aliases.
    |
    */

    'guard_api_group' => env('SERVICE_TOKENS_GUARD_API_GROUP', true),

    /*
    |--------------------------------------------------------------------------
    | last_used_at write interval
    |--------------------------------------------------------------------------
    |
    | Minimum number of seconds between last_used_at writes for a token, so
    | busy services do not write on every request. 0 writes every time.
    |
    */

    'last_used_interval' => 60,

];
