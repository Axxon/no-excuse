<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $member, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Réinitialisation de votre accès no-excuse');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.password-reset');
    }

    public function resetUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/reset-password?token='.urlencode($this->token).'&email='.urlencode($this->member->email);
    }
}
