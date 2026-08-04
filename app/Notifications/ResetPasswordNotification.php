<?php

namespace App\Notifications;

use App\Support\Mail\FichoChatMail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function __construct(#[\SensitiveParameter] string $token)
    {
        parent::__construct($token);
        $this->locale('fr');
        $this->onQueue('emails');
    }

    protected function buildMailMessage($url): MailMessage
    {
        $minutes = (int) config(
            'auth.passwords.'.config('auth.defaults.passwords').'.expire',
            60,
        );

        return FichoChatMail::make()
            ->subject('Réinitialisation du mot de passe — FichoChat')
            ->greeting('Réinitialisation du mot de passe')
            ->line('Nous avons reçu une demande de réinitialisation pour votre compte FichoChat.')
            ->action('Choisir un nouveau mot de passe', $url)
            ->line("Ce lien expire dans {$minutes} minutes.")
            ->line('Si vous n’êtes pas à l’origine de cette demande, aucune action n’est requise.');
    }

    /**
     * Lien frontend (API JWT — pas de route web password.reset).
     */
    protected function resetUrl($notifiable): string
    {
        $frontend = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')), '/');

        return sprintf(
            '%s/reset-password?token=%s&email=%s',
            $frontend,
            urlencode($this->token),
            urlencode($notifiable->getEmailForPasswordReset()),
        );
    }
}
