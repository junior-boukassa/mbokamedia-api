<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use App\Services\AdminAuditLogger;
use App\Support\MediaPath;
use Illuminate\Http\JsonResponse;

class SettingController extends ApiController
{
    public function __construct(
        protected AdminAuditLogger $adminAuditLogger,
    ) {
    }

    public function show(): JsonResponse
    {
        $setting = Setting::query()->firstOrCreate([], [
            'site_name' => config('app.name'),
        ]);

        $this->authorize('view', $setting);

        return $this->success(SettingResource::make($setting), 'Settings retrieved successfully.');
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $setting = Setting::query()->firstOrCreate([], [
            'site_name' => config('app.name'),
        ]);

        $this->authorize('update', $setting);
        $data = $request->validated();
        $data['logo'] = MediaPath::normalize($data['logo'] ?? null);
        $data['favicon'] = MediaPath::normalize($data['favicon'] ?? null);

        $setting->update($data);
        $this->adminAuditLogger->log(
            'settings.updated',
            actor: $request->user(),
            request: $request,
            target: $setting->fresh(),
            targetType: 'settings',
        );

        return $this->success(SettingResource::make($setting->fresh()), 'Settings updated successfully.');
    }
}
