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
        $dashboardUrl   = config('app.dashboard_url', env('APP_DASHBOARD_URL', 'https://dashboard.wordcastlive.site'));
        $this->setupUrl = rtrim($dashboardUrl, '/') . '/setup/' . $token;
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
