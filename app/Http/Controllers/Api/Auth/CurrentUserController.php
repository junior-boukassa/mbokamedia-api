<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentUserController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        return $this->success(
            UserResource::make($request->user()->load('roles')),
            'Current user retrieved successfully.',
        );
    }
}
