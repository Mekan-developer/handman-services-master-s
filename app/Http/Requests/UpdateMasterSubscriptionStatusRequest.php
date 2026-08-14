<?php

namespace App\Http\Requests;

use App\Enums\SubscriptionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMasterSubscriptionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
        ];
    }
}
