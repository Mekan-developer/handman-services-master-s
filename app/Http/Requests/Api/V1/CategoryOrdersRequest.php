<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CategoryOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }

    public function categoryId(): ?int
    {
        $categoryId = $this->validated('category_id');

        return $categoryId !== null ? (int) $categoryId : null;
    }
}
