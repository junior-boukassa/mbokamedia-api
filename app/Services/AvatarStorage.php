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
        $diskName = $this->disk();
        $disk = Storage::disk($diskName);

        $this->ensurePublicDirectories($directory, $diskName);

        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension());
        $path = $file->storeAs($directory, Str::uuid().'.'.$extension, [
            'disk' => $diskName,
            'visibility' => 'public',
        ]);

        if ($path === false) {
            throw new RuntimeException('Unable to store the user avatar.');
        }

        if (! $disk->setVisibility($path, 'public')) {
            $disk->delete($path);

            throw new RuntimeException('Unable to make the user avatar publicly readable.');
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

    private function ensurePublicDirectories(string $directory, string $diskName): void
    {
        if (config("filesystems.disks.{$diskName}.driver") !== 'local') {
            return;
        }

        $disk = Storage::disk($diskName);
        $path = '';

        foreach (explode('/', trim($directory, '/')) as $segment) {
            $path = $path === '' ? $segment : $path.'/'.$segment;

            if (! $disk->makeDirectory($path) || ! $disk->setVisibility($path, 'public')) {
                throw new RuntimeException('Unable to make the avatar directory publicly accessible.');
            }
        }
    }
}
