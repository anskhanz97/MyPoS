<?php

use App\Domain\Auth\Exceptions\InvalidApproval;
use App\Domain\Auth\Exceptions\InvalidPin;
use App\Domain\Catalog\Exceptions\InvalidBundleStructure;
use App\Domain\Devices\Exceptions\InvalidDeviceCredential;
use App\Domain\Tenancy\Exceptions\DataModeLocked;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\RequireActiveUser;
use App\Http\Middleware\RequirePlatformOperator;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias(['active.user' => RequireActiveUser::class, 'platform.operator' => RequirePlatformOperator::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            if ($exception instanceof AuthenticationException) {
                $status = 401;
            }
            if ($exception instanceof ValidationException) {
                $status = 422;
            }
            if ($exception instanceof DataModeLocked) {
                $status = 409;
            }
            if ($exception instanceof InvalidBundleStructure) {
                $status = 409;
            }
            if ($exception instanceof InvalidPin) {
                $status = 401;
            }
            if ($exception instanceof InvalidDeviceCredential) {
                $status = 401;
            }
            if ($exception instanceof InvalidApproval) {
                $status = 409;
            }

            $codes = [401 => 'unauthenticated', 403 => 'forbidden', 404 => 'not_found', 409 => 'invalid_state_transition', 422 => 'validation_failed', 429 => 'rate_limited'];
            $titles = [401 => 'Unauthenticated', 403 => 'Forbidden', 404 => 'Not found', 409 => 'Invalid state transition', 422 => 'Validation failed', 429 => 'Rate limited'];

            $payload = [
                'type' => 'about:blank',
                'title' => $titles[$status] ?? 'Server error',
                'status' => $status,
                'code' => $codes[$status] ?? 'dependency_unavailable',
                'detail' => $status >= 500 ? null : $exception->getMessage(),
                'request_id' => $request->attributes->get('request_id'),
                'retryable' => $status >= 500 || $status === 429,
            ];

            if ($exception instanceof InvalidPin || $exception instanceof InvalidDeviceCredential) {
                $payload['code'] = 'invalid_credentials';
            }
            if ($exception instanceof InvalidApproval) {
                $payload['code'] = 'invalid_approval';
            }

            if ($exception instanceof ValidationException) {
                $payload['field_errors'] = collect($exception->errors())
                    ->flatMap(fn (array $messages, string $field) => collect($messages)->map(fn (string $message): array => [
                        'pointer' => '/'.str_replace('.', '/', $field),
                        'code' => 'invalid',
                        'message' => $message,
                    ]))
                    ->values()
                    ->all();
            }

            return response()->json($payload, $status, ['Content-Type' => 'application/problem+json']);
        });
    })->create();
