<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email a new staff member receives the moment their account is created,
 * and the one a roster entry receives when a super admin sends an invite.
 *
 * This is deliberately NOT a password reset. The account has never been used,
 * so the copy says "set up your account" and the link lives for 24 hours (the
 * `invites` broker in config/auth.php) instead of the hour a genuine reset
 * gets. Setting a password is the whole activation: no email confirmation
 * step, no second factor.
 */
class AccountSetupInvitation extends Notification
{
    public function __construct(
        public readonly string $token,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name');
        $hours = max(1, (int) round(config('auth.passwords.invites.expire') / 60));

        return (new MailMessage)
            ->subject("Set up your {$appName} account")
            ->greeting("Hi {$notifiable->name},")
            ->line("An account has been created for you on {$appName}, the staff app for High-Performance Sports.")
            ->line('Choose a password to activate it — that is the only step. From then on you sign in with your email address and that password.')
            ->action('Set up your account', $this->setupUrl($notifiable))
            ->line("This link expires in {$hours} hours. If it runs out, ask your administrator to send you a new one.")
            ->line('If you were not expecting this email, you can ignore it.')
            ->salutation('— High-Performance Sports');
    }

    protected function setupUrl(object $notifiable): string
    {
        return url(route('password.setup', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }
}
