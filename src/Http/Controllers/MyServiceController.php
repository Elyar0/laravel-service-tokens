<?php

namespace Elyar\ServiceTokens\Http\Controllers;

use Elyar\ServiceTokens\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class MyServiceController extends Controller
{
    public function myAccess(): JsonResponse
    {
        $service = serviceAuth()->service();
        return ApiResponse::success($service->permissions, extra: ['super' => $service->super]);
    }
}
