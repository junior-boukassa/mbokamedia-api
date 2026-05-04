<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\UpdateAdvertisingRequestStatusRequest;
use App\Http\Resources\AdvertisingRequestResource;
use App\Models\AdvertisingRequest;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvertisingRequestController extends ApiController
{
    public function __construct(
        protected AdminAuditLogger $adminAuditLogger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AdvertisingRequest::class);

        $items = AdvertisingRequest::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search');
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('full_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('company_name', 'like', '%'.$search.'%')
                        ->orWhere('article_title', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->paginate((int) $request->integer('per_page', config('api.pagination.per_page')));

        return $this->paginated(AdvertisingRequestResource::collection($items), 'Advertising requests retrieved successfully.');
    }

    public function show(AdvertisingRequest $advertisingRequest): JsonResponse
    {
        $this->authorize('view', $advertisingRequest);

        return $this->success(
            AdvertisingRequestResource::make($advertisingRequest),
            'Advertising request retrieved successfully.',
        );
    }

    public function updateStatus(UpdateAdvertisingRequestStatusRequest $request, AdvertisingRequest $advertisingRequest): JsonResponse
    {
        $this->authorize('update', $advertisingRequest);

        $advertisingRequest->update([
            'status' => $request->string('status')->toString(),
        ]);

        $this->adminAuditLogger->log(
            'advertising_request.updated',
            actor: $request->user(),
            request: $request,
            target: $advertisingRequest->fresh(),
            metadata: [
                'status' => $advertisingRequest->status?->value,
                'package_type' => $advertisingRequest->package_type,
                'payment_reference' => $advertisingRequest->payment_reference,
            ],
        );

        return $this->success(
            AdvertisingRequestResource::make($advertisingRequest->fresh()),
            'Advertising request status updated successfully.',
        );
    }
}
