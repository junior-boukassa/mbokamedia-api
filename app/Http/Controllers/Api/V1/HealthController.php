<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends ApiController
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->getPdo();

            return $this->success([
                'status' => 'ok',
                'app' => config('app.name'),
                'environment' => app()->environment(),
                'database' => 'up',
                'timestamp' => now()->toIso8601String(),
            ], 'API is healthy.');
        } catch (Throwable $exception) {
            return $this->error('API health check failed.', 503, [
                'database' => ['Unable to reach the database.'],
            ]);
        }
    }
}
