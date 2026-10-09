<?php

namespace App\Repositories;

use App\Enums\SubscriptionRequestStatus;
use App\Models\Client;
use App\Models\MasterSubscription;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SubscriptionRequestRepository
{
    /**
     * Admin queue. Pending requests are worked through oldest first; the
     * reviewed ones read as history, newest first.
     *
     * @param  array{status?: string}  $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $status = $filters['status'] ?? null;

        return SubscriptionRequest::with(['client.master', 'plan', 'reviewer:id,name', 'subscription'])
            ->when($status, fn ($q, $value) => $q->where('status', $value))
            ->when(
                $status === SubscriptionRequestStatus::Pending->value,
                fn ($q) => $q->oldest('id'),
                fn ($q) => $q->latest('id'),
            )
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Everything the client has asked for, newest first — the app's request history. */
    public function forClient(Client $client): Collection
    {
        return SubscriptionRequest::with('plan')
            ->where('client_id', $client->id)
            ->latest('id')
            ->get();
    }

    /** The one request still waiting for a verdict, if any. */
    public function pendingForClient(Client $client): ?SubscriptionRequest
    {
        return SubscriptionRequest::with('plan')
            ->where('client_id', $client->id)
            ->where('status', SubscriptionRequestStatus::Pending)
            ->first();
    }

    /**
     * Same as {@see pendingForClient()} but holds a row lock on the client, so two
     * quick taps on "Buy" cannot both pass the "one pending request" check.
     * Must run inside a transaction.
     */
    public function pendingForClientLocked(Client $client): ?SubscriptionRequest
    {
        Client::query()->whereKey($client->id)->lockForUpdate()->first();

        return $this->pendingForClient($client);
    }

    public function countPending(): int
    {
        return SubscriptionRequest::query()->where('status', SubscriptionRequestStatus::Pending)->count();
    }

    public function findOrFail(int $id): SubscriptionRequest
    {
        return SubscriptionRequest::with(['client', 'plan'])->findOrFail($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): SubscriptionRequest
    {
        return SubscriptionRequest::create($data);
    }

    /** Record the administrator's verdict, plus the subscription it produced on approval. */
    public function review(
        SubscriptionRequest $request,
        SubscriptionRequestStatus $status,
        User $reviewer,
        ?string $rejectionReason = null,
        ?MasterSubscription $subscription = null,
    ): SubscriptionRequest {
        $request->update([
            'status' => $status,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => $rejectionReason,
            'master_subscription_id' => $subscription?->id,
        ]);

        return $request->refresh();
    }
}
