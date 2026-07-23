<?php

use App\Exceptions\ContactMailException;
use App\Http\Middleware\LogContactRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'log.contact' => LogContactRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $calculateDurationMs = static function (Request $request): int {
            $startedAt = $request->attributes->get('contact_request_started_at');

            if (! is_float($startedAt) && ! is_int($startedAt)) {
                return 0;
            }

            return (int) round((microtime(true) - (float) $startedAt) * 1000);
        };

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (ContactMailException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Сервис отправки сообщений временно недоступен.',
            ], 503);
        });

        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request) use ($calculateDurationMs) {
            if (! $request->is('api/*')) {
                return null;
            }

            Log::warning('Contact request rate limit exceeded.', [
                'path' => $request->path(),
                'method' => $request->method(),
                'status' => 429,
                'duration_ms' => $calculateDurationMs($request),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Слишком много запросов. Попробуйте позже.',
            ], 429);
        });

        $exceptions->render(function (Throwable $exception, Request $request) use ($calculateDurationMs) {
            if (! $request->is('api/*') || $exception instanceof HttpExceptionInterface) {
                return null;
            }

            Log::error('Unexpected API exception.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'path' => $request->path(),
                'method' => $request->method(),
                'status' => 500,
                'duration_ms' => $calculateDurationMs($request),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Произошла внутренняя ошибка.',
            ], 500);
        });
    })->create();
