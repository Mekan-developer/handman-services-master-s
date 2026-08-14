<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueMasterSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `price_paid` is optional: omitted it falls back to the plan price, and 0
     * is a legitimate value for granting free access.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subscription_plan_id' => ['required', 'integer', Rule::exists('subscription_plans', 'id')->whereNull('deleted_at')],
            'price_paid' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
