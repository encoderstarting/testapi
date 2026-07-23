<?php

declare(strict_types=1);

namespace App\Mail;

use App\DTO\AiAnalysisResult;
use App\DTO\ContactData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class ContactOwnerMail extends Mailable
{
    use Queueable;

    public function __construct(
        public ContactData $contactData,
        public AiAnalysisResult $analysisResult,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Новое обращение с формы обратной связи',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-owner',
        );
    }
}
