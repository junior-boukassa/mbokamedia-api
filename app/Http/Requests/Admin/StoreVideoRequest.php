<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $sourceType = (string) $this->input('source_type', 'external_url');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('videos', 'slug')],
            'description' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'string', 'max:255'],
            'video_url' => $sourceType === 'upload'
                ? ['nullable', 'string', 'max:2048']
                : ['nullable', 'url', 'max:2048'],
            'youtube_url' => ['nullable', 'url', 'max:2048'],
            'external_url' => ['nullable', 'url', 'max:2048'],
            'source_type' => ['required', 'in:external_url,embed,upload'],
            'status' => ['required', 'in:draft,scheduled,published,archived'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_reel' => ['sometimes', 'boolean'],
            'views_count' => ['sometimes', 'integer', 'min:0'],
            'published_at' => ['nullable', 'date'],
            'author_id' => ['nullable', 'exists:users,id'],
        ];
    }
}
