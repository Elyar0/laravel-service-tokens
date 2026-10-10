<?php

namespace Elyar\ServiceTokens\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class MyServiceController extends Controller
{
    public function myAccess(): JsonResponse
    {
        $service = serviceAuth()->service();
        return apiResponse()->success($service->permissions, extra: ['super' => $service->super]);
    }
}
