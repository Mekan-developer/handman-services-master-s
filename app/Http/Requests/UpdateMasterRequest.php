<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `access_expires_at` is intentionally absent: it is derived from the master's
     * subscriptions and must never be written by hand.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('masters', 'phone')->ignore($this->route('master'))],
            'is_active' => ['required', 'boolean'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ];
    }
}
