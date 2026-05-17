<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class CustomVerifyEmail extends VerifyEmail
{
    use Queueable;

    protected function verificationUrl($notifiable): string
    {
        $laravelUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        $query = parse_url($laravelUrl, PHP_URL_QUERY);
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

        return "{$frontendUrl}/verify-email?{$query}&id={$notifiable->getKey()}&hash=" . sha1($notifiable->getEmailForVerification());
    }

    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verify Your Email Address')
            ->greeting('Hello, ' . $notifiable->first_name . '!')
            ->line('Please click the button below to verify your email address.')
            ->action('Verify Email', $verificationUrl)
            ->line('This link will expire in 60 minutes.')
            ->line('If you did not create an account, no further action is required.');
    }
}