<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class SettingController extends ApiController
{
    public function show(): JsonResponse
    {
        $setting = Setting::query()->firstOrCreate([], [
            'site_name' => config('app.name'),
        ]);

        return $this->success(SettingResource::make($setting), 'Public settings retrieved successfully.');
    }
}
