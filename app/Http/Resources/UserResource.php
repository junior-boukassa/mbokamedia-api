<?php

namespace App\Http\Resources;

use App\Support\AdminRoles;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $shouldExposePermissions = $request->is('api/auth/login')
            || $request->is('api/v1/auth/login')
            || $request->is('api/auth/me')
            || $request->is('api/v1/auth/me')
            || $request->user()?->is($this->resource)
            || $request->user()?->can('assign roles');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_path' => $this->avatar_path,
            'job_title' => $this->job_title,
            'bio' => $this->bio,
            'is_active' => $this->is_active,
            'roles' => $this->whenLoaded('roles', fn () => AdminRoles::normalizeMany($this->getRoleNames())->values()),
            'permissions' => $this->when($shouldExposePermissions, fn () => $this->getAllPermissions()->pluck('name')->values()),
            'must_change_password' => $this->must_change_password,
            'temporary_password_set_at' => $this->temporary_password_set_at,
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
        ];
    }
}
