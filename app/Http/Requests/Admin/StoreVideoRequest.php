<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ValidatesReelFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVideoRequest extends FormRequest
{
    use ValidatesReelFile;

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
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'width' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'height' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'notify_on_publish' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'author_id' => ['nullable', 'exists:users,id'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->ensureReelIsMp4(
                $validator,
                $this->boolean('is_reel'),
                $this->input('source_type'),
                $this->input('video_url'),
            ),
        ];
    }
}
