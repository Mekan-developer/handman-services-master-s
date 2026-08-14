<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_ru' => $this->name_ru,
            'name_tk' => $this->name_tk,
            'description' => $this->description,
            'description_ru' => $this->description_ru,
            'description_tk' => $this->description_tk,
            'duration_days' => $this->duration_days,
            'price' => (float) $this->price,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'subscriptions_count' => (int) ($this->subscriptions_count ?? 0),
            'created_at' => $this->created_at->format('d.m.Y'),
        ];
    }
}
