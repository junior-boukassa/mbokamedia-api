<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewAdminWelcomeNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $loginUrl,
        protected string $email,
        protected string $temporaryPassword,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Bienvenue sur Mboka Media Admin')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Votre acces administrateur Mboka Media a ete cree.')
            ->line('Email de connexion : '.$this->email)
            ->line('Mot de passe temporaire : '.$this->temporaryPassword)
            ->action('Ouvrir le back-office', $this->loginUrl)
            ->line('Pour des raisons de securite, ce mot de passe devra etre change lors de votre premiere connexion.');
    }
}
