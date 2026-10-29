<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesReelFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateVideoRequest extends FormRequest
{
    use ValidatesReelFile;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $videoId = $this->route('video')?->id;
        $sourceType = (string) $this->input('source_type', 'external_url');

        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('videos', 'slug')->ignore($videoId)],
            'description' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'string', 'max:255'],
            'video_url' => $sourceType === 'upload'
                ? ['sometimes', 'nullable', 'string', 'max:2048']
                : ['sometimes', 'nullable', 'url', 'max:2048'],
            'youtube_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'external_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'source_type' => ['sometimes', 'in:external_url,embed,upload'],
            'status' => ['sometimes', 'in:draft,scheduled,published,archived'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_reel' => ['sometimes', 'boolean'],
            'views_count' => ['sometimes', 'integer', 'min:0'],
            'duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:86400'],
            'width' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
            'height' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
            'notify_on_publish' => ['sometimes', 'boolean'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'author_id' => ['sometimes', 'nullable', 'exists:users,id'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // Une modification sans rapport (mise en avant, titre…) ne doit
                // pas être bloquée par un ancien reel qui ne serait pas en MP4.
                if (! $this->hasAny(['is_reel', 'source_type', 'video_url'])) {
                    return;
                }

                $video = $this->route('video');

                $sourceType = $this->has('source_type')
                    ? $this->input('source_type')
                    : ($video?->source_type?->value);

                $this->ensureReelIsMp4(
                    $validator,
                    $this->has('is_reel') ? $this->boolean('is_reel') : (bool) $video?->is_reel,
                    $sourceType,
                    $this->has('video_url') ? $this->input('video_url') : $video?->video_url,
                );
            },
        ];
    }
}
