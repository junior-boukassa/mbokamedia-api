<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ContactStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\UpdateContactStatusRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Services\AdminAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends ApiController
{
    public function __construct(
        protected AdminAuditLogger $adminAuditLogger,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Contact::class);

        $contacts = Contact::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate((int) $request->integer('per_page', config('api.pagination.per_page')));

        return $this->paginated(ContactResource::collection($contacts), 'Contacts retrieved successfully.');
    }

    public function show(Contact $contact): JsonResponse
    {
        $this->authorize('view', $contact);

        return $this->success(ContactResource::make($contact), 'Contact retrieved successfully.');
    }

    public function update(UpdateContactStatusRequest $request, Contact $contact): JsonResponse
    {
        $this->authorize('update', $contact);

        $status = $request->string('status')->value();

        $contact->update([
            'status' => $status,
            'read_at' => $status === ContactStatus::Read->value ? now() : null,
        ]);
        $this->adminAuditLogger->log(
            'contact.updated',
            actor: $request->user(),
            request: $request,
            target: $contact->fresh(),
            metadata: [
                'status' => $status,
            ],
        );

        return $this->success(ContactResource::make($contact->fresh()), 'Contact updated successfully.');
    }
}
