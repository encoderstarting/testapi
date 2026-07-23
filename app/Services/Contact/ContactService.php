<?php

declare(strict_types=1);

namespace App\Services\Contact;

use App\DTO\AiAnalysisResult;
use App\DTO\ContactData;
use App\Services\Ai\AiServiceInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ContactService
{
    public function __construct(
        private readonly AiServiceInterface $aiService,
    ) {}

    public function handle(ContactData $contactData): AiAnalysisResult
    {
        try {
            return $this->aiService->analyze($contactData->comment);
        } catch (Throwable $exception) {
            Log::warning('AI analysis failed, fallback applied.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'email_masked' => $this->maskEmail($contactData->email),
                'phone_masked' => $this->maskPhone($contactData->phone),
            ]);

            return AiAnalysisResult::fallbackFromComment($contactData->comment);
        }
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
