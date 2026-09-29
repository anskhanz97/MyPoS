<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Models\SecurityAuditEvent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function store(LoginRequest $request): AuthenticatedUserResource
    {
        $credentials = [
            'email' => $request->string('email')->lower()->toString(),
            'password' => $request->string('password')->toString(),
            'is_active' => true,
        ];

        if (! Auth::attempt($credentials)) {
            SecurityAuditEvent::query()->create([
                'event_code' => 'password_login',
                'outcome' => 'failed',
                'metadata' => ['email_fingerprint' => hash('sha256', $credentials['email'])],
                'occurred_at' => now(),
            ]);
            throw ValidationException::withMessages(['email' => ['The provided credentials are invalid.']]);
        }

        $request->session()->regenerate();
        $user = $request->user();
        assert($user instanceof User);
        SecurityAuditEvent::query()->create([
            'actor_user_id' => $user->id,
            'event_code' => 'password_login',
            'outcome' => 'succeeded',
            'occurred_at' => now(),
        ]);

        return new AuthenticatedUserResource($user);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['data' => ['logged_out' => true]]);
    }
}
