<?php

namespace App\Http\Resources\Api\V1\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryContentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price,
            'image_url' => $this->images->first()
                ? asset("storage/{$this->images->first()->path}")
                : null,
        ];
    }
}
