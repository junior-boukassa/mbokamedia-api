<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBriefingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'edition_date' => ['required', 'date_format:Y-m-d'],
            'presenter' => ['required', 'string', 'max:255'],
            'text' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'notify_readers' => ['sometimes', 'boolean'],
            'article_ids' => ['sometimes', 'array'],
            'article_ids.*' => [
                'integer',
                Rule::exists('articles', 'id')->where(fn ($query) => $query
                    ->where('status', 'published')
                    ->whereNull('deleted_at')
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())),
            ],
        ];
    }
}
