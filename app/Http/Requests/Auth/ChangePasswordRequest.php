<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => [$this->user()?->must_change_password ? 'nullable' : 'required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ];
    }
}
