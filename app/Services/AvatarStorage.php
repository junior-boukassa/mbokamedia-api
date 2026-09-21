<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AvatarStorage
{
    public function store(UploadedFile $file): string
    {
        $directory = $this->directory().'/'.now()->format('Y/m');
        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension());
        $path = $file->storeAs($directory, Str::uuid().'.'.$extension, $this->disk());

        if ($path === false) {
            throw new RuntimeException('Unable to store the user avatar.');
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if (blank($path) || Str::startsWith($path, ['http://', 'https://'])) {
            return;
        }

        $normalizedPath = Str::after($path, '/storage/');

        $isLegacyAvatar = Str::startsWith($normalizedPath, 'avatars/');

        if (! $isLegacyAvatar && ! Str::startsWith($normalizedPath, $this->directory().'/')) {
            return;
        }

        Storage::disk($isLegacyAvatar ? 'public' : $this->disk())->delete($normalizedPath);
    }

    private function disk(): string
    {
        $configuredDisk = (string) config('api.media.disk', 'public');

        return $configuredDisk === 'local' ? 'public' : $configuredDisk;
    }

    private function directory(): string
    {
        return trim((string) config('api.media.directory', 'media'), '/').'/avatars';
    }
}
