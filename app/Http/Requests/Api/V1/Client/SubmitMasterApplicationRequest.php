<?php

namespace App\Http\Requests\Api\V1\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitMasterApplicationRequest extends FormRequest
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

            // Only leaf categories describe an actual trade — the parents are
            // just headings in the catalog.
            'category_ids' => ['required', 'array', 'min:1', 'max:10'],
            'category_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('categories', 'id')->whereNotNull('parent_id'),
            ],

            'experience_years' => ['required', 'integer', 'min:0', 'max:70'],
            'about' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
