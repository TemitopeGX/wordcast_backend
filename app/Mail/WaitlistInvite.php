<?php

namespace App\Mail;

use App\Models\Waitlist;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitlistInvite extends Mailable
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
            subject: 'Your WordCast Live beta access is ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.waitlist-invite',
        );
    }
}
