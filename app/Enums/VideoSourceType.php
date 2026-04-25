<?php

namespace App\Enums;

enum VideoSourceType: string
{
    case ExternalUrl = 'external_url';
    case Embed = 'embed';
    case Upload = 'upload';
}
