<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MailConfigurationTest extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'no-excuse — test de configuration e-mail');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.configuration-test');
    }
}
