<?php

namespace App\Enums;

enum AdvertisingRequestStatus: string
{
    case Pending = 'pending';
    case Contacted = 'contacted';
    case Paid = 'paid';
    case Approved = 'approved';
    case Published = 'published';
    case Rejected = 'rejected';
}
