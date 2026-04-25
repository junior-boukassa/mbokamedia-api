<?php

namespace App\Http\Controllers\Api\Public;

use App\Enums\NewsletterStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Public\NewsletterSubscribeRequest;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;

class NewsletterController extends ApiController
{
    public function store(NewsletterSubscribeRequest $request): JsonResponse
    {
        $subscriber = NewsletterSubscriber::query()->updateOrCreate(
            ['email' => $request->string('email')->value()],
            [
                'status' => NewsletterStatus::Subscribed,
                'subscribed_at' => now(),
                'unsubscribed_at' => null,
            ],
        );

        return $this->success([
            'email' => $subscriber->email,
            'status' => $subscriber->status->value,
        ], 'Newsletter subscription saved successfully.', 201);
    }
}
