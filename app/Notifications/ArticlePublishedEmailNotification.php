<?php

namespace App\Notifications;

use App\Models\ArticleNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ArticlePublishedEmailNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected ArticleNotification $articleNotification,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $articleUrl = sprintf(
            '%s/article/%s',
            rtrim((string) config('api.frontend.url', 'http://localhost:3000'), '/'),
            $this->articleNotification->slug,
        );

        return (new MailMessage())
            ->subject('Nouvel article publie sur Mboka Media')
            ->greeting('Bonjour,')
            ->line('Un nouvel article vient d’etre publie sur Mboka Media.')
            ->line($this->articleNotification->title)
            ->line($this->articleNotification->excerpt ?: 'Consultez l’article complet sur le site.')
            ->action('Lire l’article', $articleUrl)
            ->line('Merci de suivre Mboka Media.');
    }
}
