<?php

namespace App\Http\Controllers\Api\Public;

use App\Enums\AdvertisingRequestStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Public\StoreAdvertisingRequest;
use App\Http\Resources\AdvertisingRequestResource;
use App\Models\AdvertisingRequest;
use App\Models\Setting;
use App\Notifications\AdvertisingRequestAdminNotification;
use App\Notifications\AdvertisingRequestCustomerNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class AdvertisingRequestController extends ApiController
{
    public function store(StoreAdvertisingRequest $request): JsonResponse
    {
        $pricing = [
            'basic' => 30,
            'standard' => 50,
            'premium' => 80,
        ];

        $advertisingRequest = AdvertisingRequest::query()->create([
            ...$request->validated(),
            'package_price' => $pricing[$request->string('package_type')->toString()],
            'status' => AdvertisingRequestStatus::Pending,
        ]);

        $advertisingRequest->forceFill([
            'payment_reference' => sprintf(
                'ADV-%s-%s',
                $advertisingRequest->id,
                Str::upper(Str::random(6)),
            ),
        ])->save();

        $this->notifyStakeholders($advertisingRequest->fresh());

        return $this->success(
            AdvertisingRequestResource::make($advertisingRequest->fresh()),
            'Advertising request submitted successfully.',
            201,
        );
    }

    protected function notifyStakeholders(AdvertisingRequest $advertisingRequest): void
    {
        $settings = Setting::query()->first();
        $adminEmail = $settings?->primary_email ?: env('ADVERTISING_NOTIFICATIONS_EMAIL', 'contact@mbokamedia.com');

        Notification::route('mail', $adminEmail)
            ->notify(new AdvertisingRequestAdminNotification($advertisingRequest));

        Notification::route('mail', $advertisingRequest->email)
            ->notify(new AdvertisingRequestCustomerNotification($advertisingRequest));
    }
}
