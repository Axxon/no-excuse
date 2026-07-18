<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $member, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre invitation à rejoindre no-excuse');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.team-invitation');
    }

    public function activationUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/activate?token='.urlencode($this->token).'&email='.urlencode($this->member->email);
    }
}
