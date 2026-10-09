<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveSubscriptionRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The plan comes from the request itself. `price_paid` is optional: omitted
     * it falls back to the plan price, and 0 grants free access.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'price_paid' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
