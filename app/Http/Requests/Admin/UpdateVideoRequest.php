<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVideoRequest extends FormRequest
{
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
            'published_at' => ['sometimes', 'nullable', 'date'],
            'author_id' => ['sometimes', 'nullable', 'exists:users,id'],
        ];
    }
}
