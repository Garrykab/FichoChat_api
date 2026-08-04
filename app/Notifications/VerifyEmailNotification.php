<?php

namespace App\Notifications;

use App\Support\Mail\FichoChatMail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->locale('fr');
        $this->onQueue('emails');
    }

    protected function buildMailMessage($url): MailMessage
    {
        return FichoChatMail::make()
            ->subject('Confirmez votre adresse e-mail — FichoChat')
            ->greeting('Bienvenue sur FichoChat')
            ->line('Merci de vous être inscrit. Confirmez votre adresse e-mail pour activer votre compte et démarrer des conversations chiffrées.')
            ->action('Confirmer mon e-mail', $url)
            ->line('Ce lien expire bientôt. Si vous n’avez pas créé de compte, vous pouvez ignorer cet e-mail.');
    }

    /**
     * Build an API-friendly verification URL (id + hash).
     */
    protected function verificationUrl($notifiable): string
    {
        $frontend = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')), '/');

        $id = $notifiable->getKey();
        $hash = sha1($notifiable->getEmailForVerification());

        return sprintf(
            '%s/verify-email/confirm?id=%s&hash=%s',
            $frontend,
            urlencode((string) $id),
            urlencode($hash),
        );
    }
}
