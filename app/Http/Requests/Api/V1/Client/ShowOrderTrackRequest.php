<?php

namespace App\Http\Requests\Api\V1\Client;

use Illuminate\Foundation\Http\FormRequest;

class ShowOrderTrackRequest extends FormRequest
{
    /** Ownership of the order is settled by the repository, not here. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Timestamp of the last point the app already holds. Lets a phone
            // coming back from a dead connection fetch only the missing tail.
            'since' => ['nullable', 'date'],
        ];
    }
}
