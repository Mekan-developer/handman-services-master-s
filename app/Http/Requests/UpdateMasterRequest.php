<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `access_expires_at` is intentionally absent: it is derived from the master's
     * subscriptions and must never be written by hand. `name` and `phone` are also
     * absent — they are edited on the client account and kept in sync from there
     * by ClientObserver.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'is_active' => ['required', 'boolean'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ];
    }
}
