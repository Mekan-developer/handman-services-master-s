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
     * The plan is optional: approving and selling the subscription usually happen
     * together, but the owner may approve first and take the payment later.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subscription_plan_id' => ['nullable', 'integer', Rule::exists('subscription_plans', 'id')->whereNull('deleted_at')],
            'subscription_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'subscription_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
