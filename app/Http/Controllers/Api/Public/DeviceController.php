<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Public\DeviceRegisterRequest;
use App\Models\Device;
use Illuminate\Http\JsonResponse;

class DeviceController extends ApiController
{
    /**
     * L'application appelle cette route à chaque lancement et à chaque
     * renouvellement de token : l'opération doit être idempotente.
     */
    public function store(DeviceRegisterRequest $request): JsonResponse
    {
        $token = $request->string('token')->value();

        $device = Device::query()->updateOrCreate(
            ['token_hash' => Device::hashFor($token)],
            [
                'token' => $token,
                'platform' => $request->string('platform')->value(),
                'app_version' => $request->input('app_version'),
                'locale' => $request->input('locale'),
                'last_seen_at' => now(),
            ],
        );

        return $this->success([
            'platform' => $device->platform,
        ], 'Appareil enregistré.', 201);
    }
}
