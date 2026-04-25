<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeaturedSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $sectionId = $this->route('featured_section')?->id ?? $this->route('featuredSection')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('featured_sections', 'slug')->ignore($sectionId)],
            'headline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'max_items' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'items' => ['nullable', 'array'],
            'items.*.featureable_type' => ['nullable', 'in:article,video'],
            'items.*.featureable_id' => ['nullable', 'integer'],
            'items.*.manual_title' => ['nullable', 'string', 'max:255'],
            'items.*.manual_excerpt' => ['nullable', 'string'],
            'items.*.manual_url' => ['nullable', 'url', 'max:2048'],
            'items.*.manual_image' => ['nullable', 'string', 'max:255'],
            'items.*.sort_order' => ['sometimes', 'integer', 'min:0'],
            'items.*.is_active' => ['sometimes', 'boolean'],
        ];
    }
}
