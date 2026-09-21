<?php

namespace App\Http\Resources;

use App\Support\AdminRoles;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $shouldExposePermissions = $request->is('api/auth/login')
            || $request->is('api/auth/verify-otp')
            || $request->is('api/auth/resend-otp')
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
            'avatar_url' => $this->avatarUrl(),
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

    private function avatarUrl(): ?string
    {
        $path = trim((string) $this->avatar_path);

        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $storagePath = match (true) {
            Str::startsWith($path, '/storage/') => Str::after($path, '/storage/'),
            Str::startsWith($path, 'storage/') => Str::after($path, 'storage/'),
            default => ltrim($path, '/'),
        };
        $avatarDirectory = trim((string) config('api.media.directory', 'media'), '/').'/avatars/';
        $diskName = Str::startsWith($storagePath, $avatarDirectory)
            ? (string) config('api.media.disk', 'public')
            : 'public';
        $diskName = $diskName === 'local' ? 'public' : $diskName;
        $disk = Storage::disk($diskName);

        if (! $disk->exists($storagePath)) {
            return null;
        }

        $diskUrl = $disk->url($storagePath);

        if (Str::startsWith($diskUrl, ['http://', 'https://'])) {
            return $diskUrl;
        }

        $publicStorageUrl = rtrim((string) config('filesystems.disks.public.url'), '/');

        if (! Str::startsWith($publicStorageUrl, ['http://', 'https://'])) {
            $publicStorageUrl = rtrim((string) config('app.url'), '/').'/storage';
        }

        return $publicStorageUrl.'/'.ltrim($storagePath, '/');
    }
}
