<?php

namespace App\Notifications;

use App\Support\Mail\FichoChatMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeviceRecoveryOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        #[\SensitiveParameter]
        public readonly string $code,
    ) {
        $this->locale('fr');
        $this->onQueue('emails');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return FichoChatMail::make()
            ->subject('Code de récupération — FichoChat')
            ->markdown('mail.device-recovery-otp', [
                'code' => $this->code,
            ]);
    }
}
