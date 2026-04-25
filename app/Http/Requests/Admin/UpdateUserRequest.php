<?php

namespace App\Http\Requests\Admin;

use App\Support\AdminRoles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:30'],
            'avatar_path' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(config('admin.allowed_roles', [])), Rule::exists('roles', 'name')],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('roles')) {
            return;
        }

        $this->merge([
            'roles' => AdminRoles::normalizeMany($this->input('roles', []))->all(),
        ]);
    }
}
