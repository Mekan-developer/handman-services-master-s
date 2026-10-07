<?php

namespace App\Http\Resources\Api\V1\Client;

use App\Models\ClientDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClientDevice */
class ClientDeviceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform->value,
            'locale' => $this->locale,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
