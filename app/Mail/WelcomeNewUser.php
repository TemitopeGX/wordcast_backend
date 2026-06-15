<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeNewUser extends Mailable
{
    use Queueable, SerializesModels;

    public string $dashboardUrl;
    public string $downloadUrl;
    public string $telegramUrl;

    public function __construct(public User $user, public string $licenseKey)
    {
        $this->dashboardUrl = config('app.dashboard_url', env('APP_DASHBOARD_URL', 'https://dashboard.wordcastlive.site'));
        $this->downloadUrl  = env('APP_DOWNLOAD_URL', 'https://download.wordcastlive.site/latest/WordCast-Live-Setup.exe');
        $this->telegramUrl  = env('APP_TELEGRAM_URL', 'https://t.me/wordcastlive');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to WordCast Live — Your account is ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome-new-user',
        );
    }
}
