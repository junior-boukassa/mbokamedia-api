<?php

namespace App\Models;

use App\Enums\AdvertisingRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdvertisingRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'company_name',
        'website_url',
        'article_title',
        'description',
        'image_url',
        'package_type',
        'package_price',
        'message',
        'status',
        'payment_reference',
    ];

    protected function casts(): array
    {
        return [
            'package_price' => 'decimal:2',
            'status' => AdvertisingRequestStatus::class,
        ];
    }
}
