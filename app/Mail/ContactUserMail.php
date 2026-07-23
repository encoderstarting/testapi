<?php

declare(strict_types=1);

namespace App\Mail;

use App\DTO\ContactData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class ContactUserMail extends Mailable
{
    use Queueable;

    public function __construct(
        public ContactData $contactData,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Мы получили ваше обращение',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-user',
        );
    }
}
