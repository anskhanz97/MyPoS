<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BootstrapController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => ['authenticated' => true]]);
    }
}
