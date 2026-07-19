<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MailConfigurationTest extends Mailable
{
    public function __construct(
        private readonly ?string $senderName = null,
        private readonly ?string $organizationReplyTo = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->senderName
                ? new Address((string) config('mail.from.address'), $this->senderName)
                : null,
            replyTo: $this->organizationReplyTo ? [new Address($this->organizationReplyTo)] : [],
            subject: 'no-excuse — test de configuration e-mail',
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.configuration-test');
    }
}
