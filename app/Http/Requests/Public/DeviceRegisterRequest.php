<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'min:32', 'max:4096'],
            'platform' => ['required', 'string', Rule::in(['android', 'ios'])],
            'app_version' => ['nullable', 'string', 'max:32'],
            'locale' => ['nullable', 'string', 'max:16'],
        ];
    }
}
