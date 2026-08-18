<?php

namespace App\Http\Requests\Api\V1\Client;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'city_id' => ['sometimes', 'integer', 'exists:cities,id'],

            // Multipart only, so the app has to spoof the method:
            // POST /client/me with `_method=PATCH`.
            'photo' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ];
    }
}
