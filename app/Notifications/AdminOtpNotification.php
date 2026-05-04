<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $code,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Code de verification Mboka Media Admin')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Une verification supplementaire est requise pour acceder au back-office Mboka Media.')
            ->line('Votre code de verification est : '.$this->code)
            ->line('Ce code expire dans 10 minutes et ne peut etre utilise que 5 fois maximum.')
            ->line('Si vous n’etes pas a l’origine de cette tentative, vous pouvez ignorer cet email.');
    }
}
