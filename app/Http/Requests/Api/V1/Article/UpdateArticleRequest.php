<?php

namespace App\Http\Requests\Api\V1\Article;

use App\Enums\ContentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['sometimes', 'string'],
            'featured_image' => ['nullable', 'string', 'max:2048'],
            'additional_images' => ['sometimes', 'nullable', 'array', 'max:2'],
            'additional_images.*' => ['required', 'array:path,alt_text,caption,credit'],
            'additional_images.*.path' => [
                'required',
                'string',
                'max:255',
                'distinct:strict',
                Rule::exists('media', 'path')->where('type', 'image')->where('disk', 'public'),
            ],
            'additional_images.*.alt_text' => ['nullable', 'string', 'max:255'],
            'additional_images.*.caption' => ['nullable', 'string', 'max:500'],
            'additional_images.*.credit' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['sometimes', Rule::in(array_column(ContentStatus::cases(), 'value'))],
            'published_at' => ['nullable', 'date'],
            'is_featured' => ['nullable', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
        ];
    }
}
