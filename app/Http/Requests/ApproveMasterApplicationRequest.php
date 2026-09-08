<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveMasterApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A plan is required: the owner takes the payment in person at approval time,
     * so approving without dialing in a subscription would leave access closed
     * with no way back to it from this screen.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subscription_plan_id' => ['required', 'integer', Rule::exists('subscription_plans', 'id')->whereNull('deleted_at')],
            'subscription_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'subscription_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
