<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMasterSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'price_paid' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
