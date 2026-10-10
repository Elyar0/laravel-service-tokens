<?php

use Elyar\ServiceTokens\Http\Controllers\MyServiceController;
use Elyar\ServiceTokens\Http\Middleware\AuthenticateActiveServiceTokensMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('me')->middleware(AuthenticateActiveServiceTokensMiddleware::class)->controller(MyServiceController::class)->group(function () {
    Route::get('my-access', 'myAccess');
});