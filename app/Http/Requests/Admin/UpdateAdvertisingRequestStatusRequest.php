<?php

namespace App\Http\Requests\Admin;

use App\Enums\AdvertisingRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdvertisingRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_column(AdvertisingRequestStatus::cases(), 'value'))],
        ];
    }
}
