<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdvertisingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'min:8', 'max:30'],
            'company_name' => ['required', 'string', 'min:2', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'article_title' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['required', 'string', 'min:20'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'package_type' => ['required', Rule::in(['basic', 'standard', 'premium'])],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
