<?php

namespace App\Support\Mail;

use Illuminate\Notifications\Messages\MailMessage;

final class FichoChatMail
{
    public static function make(): MailMessage
    {
        app()->setLocale('fr');

        return (new MailMessage)
            ->from(
                config('mail.from.address'),
                config('mail.from.name', 'FichoChat'),
            )
            ->salutation('L’équipe FichoChat');
    }
}
