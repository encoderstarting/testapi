<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'services' => [
                'application' => 'available',
                'ai' => $this->resolveAiStatus(),
                'mail' => $this->resolveMailStatus(),
            ],
        ]);
    }

    private function resolveAiStatus(): string
    {
        $provider = (string) config('services.ai.provider');
        $apiKey = (string) config('services.ai.api_key');
        $model = (string) config('services.ai.model');

        return $provider !== '' && $apiKey !== '' && $model !== ''
            ? 'configured'
            : 'not_configured';
    }

    private function resolveMailStatus(): string
    {
        $ownerEmail = (string) config('contact.owner_email');
        $mailer = (string) config('mail.default');
        $fromAddress = (string) config('mail.from.address');

        return $ownerEmail !== '' && $mailer !== '' && $fromAddress !== ''
            ? 'configured'
            : 'not_configured';
    }
}
