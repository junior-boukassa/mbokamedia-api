<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\NewsletterStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\UpdateNewsletterSubscriberRequest;
use App\Http\Resources\NewsletterSubscriberResource;
use App\Models\NewsletterSubscriber;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterSubscriberController extends ApiController
{
    public function __construct(
        protected AdminAuditLogger $adminAuditLogger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('manage newsletter'), 403);

        $subscribers = NewsletterSubscriber::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate((int) $request->integer('per_page', config('api.pagination.per_page')));

        return $this->paginated(NewsletterSubscriberResource::collection($subscribers), 'Subscribers retrieved successfully.');
    }

    public function update(UpdateNewsletterSubscriberRequest $request, NewsletterSubscriber $newsletterSubscriber): JsonResponse
    {
        abort_unless($request->user()->can('manage newsletter'), 403);

        $status = $request->string('status')->value();

        $newsletterSubscriber->update([
            'status' => $status,
            'subscribed_at' => $status === NewsletterStatus::Subscribed->value ? now() : $newsletterSubscriber->subscribed_at,
            'unsubscribed_at' => $status === NewsletterStatus::Unsubscribed->value ? now() : null,
        ]);
        $this->adminAuditLogger->log(
            'newsletter_subscriber.updated',
            actor: $request->user(),
            request: $request,
            target: $newsletterSubscriber->fresh(),
            metadata: [
                'status' => $status,
            ],
        );

        return $this->success(NewsletterSubscriberResource::make($newsletterSubscriber->fresh()), 'Subscriber updated successfully.');
    }
}
