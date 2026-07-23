<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class LogContactRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $request->attributes->set('contact_request_started_at', $startedAt);

        Log::info('Contact request started.', [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'email_masked' => $this->maskEmail((string) $request->input('email')),
            'phone_masked' => $this->maskPhone((string) $request->input('phone')),
        ]);

        $response = $next($request);

        Log::info('Contact request completed.', [
            'path' => $request->path(),
            'method' => $request->method(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $this->calculateDurationMs($request),
        ]);

        return $response;
    }

    private function calculateDurationMs(Request $request): int
    {
        $startedAt = $request->attributes->get('contact_request_started_at');

        if (! is_float($startedAt) && ! is_int($startedAt)) {
            return 0;
        }

        return (int) round((microtime(true) - (float) $startedAt) * 1000);
    }

    private function maskEmail(string $email): string
    {
        [$localPart, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($localPart === '' || $domain === '') {
            return 'hidden';
        }

        return mb_substr($localPart, 0, 3).'***@'.$domain;
    }

    private function maskPhone(string $phone): string
    {
        $normalized = preg_replace('/\D+/', '', $phone);

        if (! is_string($normalized) || $normalized === '') {
            return 'hidden';
        }

        return mb_substr($normalized, 0, 1).'******'.mb_substr($normalized, -2);
    }
}
