<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CandidateDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Application $application) {}

    public function envelope(): Envelope
    {
        $organization = $this->application->offer->organization;

        return new Envelope(
            from: new Address((string) config('mail.from.address'), $organization?->notification_sender_name ?? 'Équipe recrutement'),
            replyTo: $organization?->notification_reply_to ? [new Address($organization->notification_reply_to)] : [],
            subject: 'Votre candidature — '.$this->application->offer->title,
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.candidate-decision');
    }
}
