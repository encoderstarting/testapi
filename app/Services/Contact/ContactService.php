<?php

declare(strict_types=1);

namespace App\Services\Contact;

use App\DTO\AiAnalysisResult;
use App\DTO\ContactData;
use App\Exceptions\ContactMailException;
use App\Mail\ContactOwnerMail;
use App\Mail\ContactUserMail;
use App\Services\Ai\AiServiceInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class ContactService
{
    public function __construct(
        private readonly AiServiceInterface $aiService,
    ) {}

    public function handle(ContactData $contactData): AiAnalysisResult
    {
        $analysisResult = $this->analyzeComment($contactData);

        $this->sendNotifications($contactData, $analysisResult);

        return $analysisResult;
    }

    private function analyzeComment(ContactData $contactData): AiAnalysisResult
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

    private function sendNotifications(ContactData $contactData, AiAnalysisResult $analysisResult): void
    {
        $ownerEmail = $this->resolveOwnerEmail();

        try {
            Mail::to($ownerEmail)->send(new ContactOwnerMail($contactData, $analysisResult));
            Mail::to($contactData->email)->send(new ContactUserMail($contactData));
        } catch (Throwable $exception) {
            Log::error('Contact mail delivery failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'owner_email_masked' => $this->maskEmail($ownerEmail),
                'email_masked' => $this->maskEmail($contactData->email),
            ]);

            throw new ContactMailException(
                'Mail delivery failed.',
                previous: $exception,
            );
        }
    }

    private function resolveOwnerEmail(): string
    {
        $ownerEmail = (string) config('contact.owner_email');

        if ($ownerEmail === '' || ! filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            Log::error('Contact mail delivery failed.', [
                'message' => 'Owner email is not configured.',
            ]);

            throw new ContactMailException('Owner email is not configured.');
        }

        return $ownerEmail;
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
