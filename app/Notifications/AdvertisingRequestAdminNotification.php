<?php

namespace App\Notifications;

use App\Models\AdvertisingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdvertisingRequestAdminNotification extends Notification
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
            ->subject('Nouvelle demande annonceur Mboka Media')
            ->greeting('Bonjour,')
            ->line('Une nouvelle demande annonceur vient d’arriver.')
            ->line('Client : '.$this->advertisingRequest->full_name)
            ->line('Entreprise : '.$this->advertisingRequest->company_name)
            ->line('Offre : '.$this->advertisingRequest->package_type.' ('.$this->advertisingRequest->package_price.' USD)')
            ->line('Email : '.$this->advertisingRequest->email)
            ->line('Telephone : '.$this->advertisingRequest->phone)
            ->line('Reference : '.($this->advertisingRequest->payment_reference ?: 'En attente'));
    }
}
