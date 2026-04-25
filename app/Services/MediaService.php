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
        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($directory, $filename, $disk);

        return Medium::query()->create([
            'name' => $attributes['name'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'disk' => $disk,
            'path' => $path,
            'url' => Storage::disk($disk)->url($path),
            'mime_type' => $file->getClientMimeType(),
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
        $mimeType = $file->getClientMimeType() ?? '';

        return match (true) {
            str_starts_with($mimeType, 'image/') => MediaType::Image,
            str_starts_with($mimeType, 'video/') => MediaType::Video,
            str_starts_with($mimeType, 'audio/') => MediaType::Audio,
            in_array($mimeType, ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'], true) => MediaType::Document,
            default => MediaType::Other,
        };
    }

    protected function resolveDisk(): string
    {
        $configuredDisk = config('api.media.disk', 'public');

        // The media library is consumed by the public site and admin previews,
        // so local private storage would make uploads impossible to display.
        return $configuredDisk === 'local' ? 'public' : $configuredDisk;
    }
}
