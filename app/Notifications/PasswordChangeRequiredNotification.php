<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangeRequiredNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $changePasswordUrl,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Action requise : changez votre mot de passe admin')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Votre compte utilise encore un mot de passe temporaire.')
            ->line('Vous devez definir un nouveau mot de passe avant d’acceder au dashboard Mboka Media.')
            ->action('Changer mon mot de passe', $this->changePasswordUrl);
    }
}
