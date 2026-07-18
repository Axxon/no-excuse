<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DemoSlotAvailableMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $messageLocale = 'fr') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->messageLocale === 'en' ? 'A no-excuse demo slot is available' : 'Une place est disponible sur la démo no-excuse');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.demo-slot-available');
    }
}
