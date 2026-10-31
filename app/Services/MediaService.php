<?php

namespace App\Services;

use App\Enums\MediaType;
use App\Models\Medium;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    public function store(UploadedFile $file, ?User $user = null, array $attributes = []): Medium
    {
        $disk = $this->resolveDisk();
        $directory = trim(config('api.media.directory', 'media'), '/').'/'.now()->format('Y/m');
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) ?: '';
        $extension = $this->resolveExtension($mimeType, $file->getClientOriginalExtension());
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs($directory, $filename, $disk);

        return Medium::query()->create([
            'name' => $attributes['name'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'disk' => $disk,
            'path' => $path,
            'url' => Storage::disk($disk)->url($path),
            'mime_type' => $mimeType,
            'extension' => $file->getClientOriginalExtension(),
            'size' => $file->getSize(),
            'type' => $this->detectType($file),
            'alt_text' => $attributes['alt_text'] ?? null,
            'uploaded_by' => $user?->id,
        ]);
    }

    public function destroy(Medium $medium): void
    {
        Storage::disk($medium->disk)->delete($medium->path);
        $medium->delete();
    }

    protected function detectType(UploadedFile $file): MediaType
    {
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()) ?: '';

        return match (true) {
            str_starts_with($mimeType, 'image/') => MediaType::Image,
            str_starts_with($mimeType, 'video/') => MediaType::Video,
            str_starts_with($mimeType, 'audio/') => MediaType::Audio,
            in_array($mimeType, ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'], true) => MediaType::Document,
            default => MediaType::Other,
        };
    }

    protected function resolveExtension(string $mimeType, string $clientExtension): string
    {
        $extension = strtolower($clientExtension);
        $allowedExtensionsByMime = [
            'image/jpeg' => ['jpg' => 'jpg', 'jpeg' => 'jpg'],
            'image/png' => ['png' => 'png'],
            'image/webp' => ['webp' => 'webp'],
            'image/gif' => ['gif' => 'gif'],
            'video/mp4' => ['mp4' => 'mp4', 'm4v' => 'm4v'],
            'application/mp4' => ['mp4' => 'mp4', 'm4v' => 'm4v'],
            'video/webm' => ['webm' => 'webm'],
            'video/quicktime' => ['mov' => 'mov'],
            'video/x-m4v' => ['m4v' => 'm4v'],
            'video/ogg' => ['ogg' => 'ogg'],
        ];

        if (! isset($allowedExtensionsByMime[$mimeType][$extension])) {
            throw new \InvalidArgumentException('Unsupported media type or extension.');
        }

        return $allowedExtensionsByMime[$mimeType][$extension];
    }

    protected function resolveDisk(): string
    {
        $configuredDisk = config('api.media.disk', 'public');

        // The media library is consumed by the public site and admin previews,
        // so local private storage would make uploads impossible to display.
        return $configuredDisk === 'local' ? 'public' : $configuredDisk;
    }
}
