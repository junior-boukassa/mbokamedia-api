<?php

namespace App\Notifications;

use App\Models\AdvertisingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdvertisingRequestCustomerNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected AdvertisingRequest $advertisingRequest,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Votre demande annonceur a bien ete recue')
            ->greeting('Bonjour '.$this->advertisingRequest->full_name.',')
            ->line('Votre demande annonceur a bien ete transmise a l’equipe Mboka Media.')
            ->line('Offre choisie : '.$this->advertisingRequest->package_type.' ('.$this->advertisingRequest->package_price.' USD)')
            ->line('Statut initial : '.$this->advertisingRequest->status->value)
            ->line('Nous reviendrons vers vous apres validation editoriale et commerciale.');
    }
}
