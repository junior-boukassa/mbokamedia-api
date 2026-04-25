<?php

namespace App\Enums;

enum NewsletterStatus: string
{
    case Subscribed = 'subscribed';
    case Unsubscribed = 'unsubscribed';
}
