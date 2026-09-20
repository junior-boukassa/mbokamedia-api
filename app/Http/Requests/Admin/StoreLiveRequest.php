<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('lives', 'slug')],
            'description' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'string', 'max:2048'],
            'stream_type' => ['required', 'in:youtube,hls,external'],
            'stream_url' => ['nullable', 'required_if:stream_type,hls', 'url:https', 'max:2048'],
            'external_url' => ['nullable', 'required_if:stream_type,external', 'url:https', 'max:2048'],
            'status' => ['required', 'in:scheduled,live,ended,cancelled'],
            'scheduled_at' => ['nullable', 'date'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
            'is_featured' => ['sometimes', 'boolean'],
            'created_by' => ['nullable', 'exists:users,id'],
        ];
    }
}
