<?php

use Elyar\ServiceTokens\Services\ServiceAuth;

if(!function_exists('serviceAuth')){
    function serviceAuth()
    {
        return app(ServiceAuth::class);
    }
}