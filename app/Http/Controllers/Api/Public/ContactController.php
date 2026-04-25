<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Public\StoreContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;

class ContactController extends ApiController
{
    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = Contact::query()->create($request->validated());

        return $this->success(ContactResource::make($contact), 'Contact message sent successfully.', 201);
    }
}
