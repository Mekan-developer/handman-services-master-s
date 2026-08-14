<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name_ru' => ['required', 'string', 'max:255'],
            'name_tk' => ['required', 'string', 'max:255'],
            'description_ru' => ['nullable', 'string', 'max:1000'],
            'description_tk' => ['nullable', 'string', 'max:1000'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['sort_order' => $this->input('sort_order') ?? 0]);
    }
}
