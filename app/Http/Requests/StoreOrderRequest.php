<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An order always belongs to a client: either an existing one (client_id)
     * or a new one created from name + phone in CreateOrderForClientAction.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'client_id' => ['nullable', 'required_without_all:client_name,client_phone', 'integer', 'exists:clients,id'],
            'client_name' => ['nullable', 'required_without:client_id', 'string', 'max:255'],
            'client_phone' => ['nullable', 'required_without:client_id', 'string', 'max:20'],
            'description' => ['required', 'string', 'max:5000'],
            'client_address' => ['nullable', 'string', 'max:500'],
            'client_lat' => ['required', 'numeric', 'between:-90,90'],
            'client_lng' => ['required', 'numeric', 'between:-180,180'],
            'photos' => ['nullable', 'array', 'max:4'],
            'photos.*' => ['file', 'image', 'max:8192'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'client_id.required_without_all' => __('orders.validation.client_required'),
            'client_name.required_without' => __('orders.validation.client_name_required'),
            'client_phone.required_without' => __('orders.validation.client_phone_required'),
        ];
    }
}
