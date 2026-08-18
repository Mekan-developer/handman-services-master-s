<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:masters,phone'],
            'is_active' => ['required', 'boolean'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],

            // Optional first subscription — the only way a new master gets access.
            'subscription_plan_id' => ['nullable', 'integer', Rule::exists('subscription_plans', 'id')->whereNull('deleted_at')],
            'subscription_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'subscription_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
