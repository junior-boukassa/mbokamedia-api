<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdvertisingRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company_name' => $this->company_name,
            'website_url' => $this->website_url,
            'article_title' => $this->article_title,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'package_type' => $this->package_type,
            'package_price' => (float) $this->package_price,
            'message' => $this->message,
            'status' => $this->status?->value,
            'payment_reference' => $this->payment_reference,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
