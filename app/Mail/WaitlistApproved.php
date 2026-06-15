<?php

namespace App\Mail;

use App\Models\Waitlist;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitlistApproved extends Mailable
{
    use Queueable, SerializesModels;

    public string $setupUrl;

    public function __construct(public Waitlist $entry, string $token)
    {
        // The Next.js frontend has the setup route at /setup/[token], not /dashboard/setup/[token]
        $frontendUrls = explode(',', env('FRONTEND_URL', 'https://wordcastlive.site'));
        $frontendUrl = rtrim(trim($frontendUrls[0]), '/');
        $this->setupUrl = $frontendUrl . '/setup/' . $token;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been approved - Set up your WordCast Live account',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.waitlist-approved',
        );
    }
}
