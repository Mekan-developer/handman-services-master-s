<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Partial edit of a working master's own trade details. Everything is
 * `sometimes`, so the app can send just the field the master changed.
 *
 * `name`, `phone` and `city_id` live on the client account and are edited
 * through `PATCH /client/me` — ClientObserver mirrors them onto the master
 * profile, so a person has one name and one city, not two.
 * Status, activity and access deadline are the administrator's business.
 */
class UpdateMasterProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Only leaf categories describe an actual trade — the parents are
            // just headings in the catalog. Same limits as the application form.
            'category_ids' => ['sometimes', 'array', 'min:1', 'max:10'],
            'category_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('categories', 'id')->whereNotNull('parent_id'),
            ],

            'experience_years' => ['sometimes', 'integer', 'min:0', 'max:70'],
            'about' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
