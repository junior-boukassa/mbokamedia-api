<?php

namespace App\Http\Requests\Admin;

class UpdateBriefingRequest extends StoreBriefingRequest
{
    public function rules(): array
    {
        return array_map(
            static fn (array $rules): array => in_array('required', $rules, true)
                ? array_values(array_diff($rules, ['required']))
                : $rules,
            parent::rules(),
        );
    }
}
