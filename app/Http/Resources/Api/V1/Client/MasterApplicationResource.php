<?php

namespace App\Http\Resources\Api\V1\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The master profile as seen from the client side of the app — what the
 * applicant submitted plus where the application stands. Deliberately free of
 * anything operational (availability, location): once approved, the app reads
 * the master endpoints instead.
 */
class MasterApplicationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'experience_years' => $this->experience_years,
            'about' => $this->about,
            'rejection_reason' => $this->rejection_reason,
            'reviewed_at' => $this->reviewed_at?->toDateTimeString(),

            // Access is granted by a subscription the administrator issues after
            // approval, so an approved profile can still be waiting for one.
            'has_access' => $this->isApproved() && $this->is_active && $this->hasActiveAccess(),
            'access_expires_at' => $this->access_expires_at?->toDateString(),

            'city' => $this->whenLoaded('city', fn () => [
                'id' => $this->city->id,
                'name' => $this->city->name,
            ]),
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
