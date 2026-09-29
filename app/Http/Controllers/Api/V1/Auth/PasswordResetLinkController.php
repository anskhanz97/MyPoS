<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink(['email' => $request->string('email')->lower()->toString()]);

        return response()->json(['data' => ['accepted' => true]], 202);
    }
}
