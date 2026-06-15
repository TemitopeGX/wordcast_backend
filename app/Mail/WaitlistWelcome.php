<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitlistWelcome extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $licenseKey
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to WordCast Live - Your account is ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.waitlist-welcome',
        );
    }
}
