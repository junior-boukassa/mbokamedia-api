<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ApiController extends Controller
{
    protected function success(mixed $data = null, string $message = 'Request successful.', int $status = 200, array $meta = []): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    protected function paginated(AnonymousResourceCollection $collection, string $message = 'Request successful.'): JsonResponse
    {
        $payload = $collection->response()->getData(true);

        return $this->success(
            $payload['data'],
            $message,
            200,
            [
                'pagination' => $payload['meta'] ?? [],
                'links' => $payload['links'] ?? [],
            ],
        );
    }

    protected function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'data' => null,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
