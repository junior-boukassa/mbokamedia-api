<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('lives', 'slug')->ignore($this->route('live'))],
            'description' => ['sometimes', 'nullable', 'string'],
            'thumbnail' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'stream_type' => ['sometimes', 'in:youtube,hls,external'],
            'stream_url' => ['sometimes', 'nullable', 'required_if:stream_type,hls', 'url:https', 'max:2048'],
            'external_url' => ['sometimes', 'nullable', 'required_if:stream_type,external', 'url:https', 'max:2048'],
            'status' => ['sometimes', 'in:scheduled,live,ended,cancelled'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'started_at' => ['sometimes', 'nullable', 'date'],
            'ended_at' => ['sometimes', 'nullable', 'date'],
            'is_featured' => ['sometimes', 'boolean'],
            'created_by' => ['sometimes', 'nullable', 'exists:users,id'],
        ];
    }
}
