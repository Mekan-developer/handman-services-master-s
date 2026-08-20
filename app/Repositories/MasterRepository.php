<?php

namespace App\Repositories;

use App\Enums\MasterStatus;
use App\Enums\OrderStatus;
use App\Models\Client;
use App\Models\Master;
use App\Models\MasterLocation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class MasterRepository
{
    /** @param array{search?: string, city_id?: int|string, status?: string} $filters */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        // `client` carries the avatar shown in the list, `latestLocation` the GPS
        // badge — eager loaded to keep the resource from firing a query per row.
        return Master::with(['city', 'categories', 'client', 'latestLocation'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->when($filters['search'] ?? null, function ($q, $search) {
                $escaped = addcslashes($search, '%_\\');
                $q->where(fn ($sub) => $sub
                    ->where('name', 'like', "%{$escaped}%")
                    ->orWhere('phone', 'like', "%{$escaped}%")
                );
            })
            ->when($filters['city_id'] ?? null, fn ($q, $id) => $q->where('city_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Applications awaiting review, oldest first — the admin works through a queue. */
    public function pendingApplications(int $perPage = 15): LengthAwarePaginator
    {
        return Master::with(['city', 'categories', 'client'])
            ->where('status', MasterStatus::Pending)
            ->oldest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function countPendingApplications(): int
    {
        return Master::query()->where('status', MasterStatus::Pending)->count();
    }

    /**
     * Record the administrator's verdict. Access itself is not granted here —
     * that is the subscription's job (see IssueMasterSubscriptionAction).
     */
    public function review(Master $master, MasterStatus $status, User $reviewer, ?string $rejectionReason = null): Master
    {
        $master->update([
            'status' => $status,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
            'rejection_reason' => $rejectionReason,
        ]);

        return $master->refresh();
    }

    /** All active masters with latest location — for map view. */
    public function forMap(?int $cityId = null): Collection
    {
        return Master::with(['city', 'latestLocation', 'client'])
            ->where('status', MasterStatus::Approved)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('access_expires_at')
                    ->orWhere('access_expires_at', '>', now());
            })
            ->when($cityId, fn ($q, $id) => $q->where('city_id', $id))
            ->get();
    }

    /**
     * Approved masters, name-ordered — feeds admin pickers such as the
     * subscription dropdown. Applicants are excluded: a subscription sold before
     * approval would not open anything and only muddles the queue.
     */
    public function allForSelect(): Collection
    {
        return Master::with('client')
            ->where('status', MasterStatus::Approved)
            ->orderBy('name')
            ->get();
    }

    /** Location history for a single master — for trajectory. */
    public function trajectory(Master $master, int $hours = 8): Collection
    {
        return $master->locations()
            ->where('recorded_at', '>=', now()->subHours($hours))
            ->orderBy('recorded_at')
            ->get(['latitude', 'longitude', 'recorded_at']);
    }

    public function findOrFail(int $id): Master
    {
        return Master::findOrFail($id);
    }

    /**
     * The master profile attached to a client account, in any status.
     * Always re-read: callers gate access on the result, so a stale relation
     * cached on the Client model must never decide it.
     */
    public function findByClient(Client $client): ?Master
    {
        return Master::query()->where('client_id', $client->id)->first();
    }

    /** Most recent GPS ping, or null when the master has never reported a position. */
    public function latestLocation(Master $master): ?MasterLocation
    {
        return $master->locations()->latest('recorded_at')->first();
    }

    public function create(array $data): Master
    {
        $categories = $data['category_ids'] ?? [];
        unset($data['category_ids']);

        $master = Master::create($data);
        $master->categories()->sync($categories);

        return $master;
    }

    /**
     * Categories are re-synced only when `category_ids` is actually part of the
     * payload — a partial update (the master editing just their experience)
     * must not wipe the trades they are listed under.
     */
    public function update(Master $master, array $data): Master
    {
        $syncCategories = array_key_exists('category_ids', $data);
        $categories = $data['category_ids'] ?? [];
        unset($data['category_ids']);

        $master->update($data);

        if ($syncCategories) {
            $master->categories()->sync($categories);
        }

        return $master;
    }

    public function delete(Master $master): void
    {
        $master->delete();
    }

    /** Jobs the master actually finished — the history that blocks deletion. */
    public function completedOrdersCount(Master $master): int
    {
        return $master->orders()->where('status', OrderStatus::Completed)->count();
    }

    /** Jobs in flight: taken but not yet finished or cancelled. */
    public function activeOrdersCount(Master $master): int
    {
        return $master->orders()
            ->whereIn('status', [OrderStatus::Assigned, OrderStatus::InProgress])
            ->count();
    }

    /**
     * Write the access deadline derived from the master's subscriptions.
     * Separate from {@see update()} because that one also re-syncs categories.
     */
    public function updateAccessExpiry(Master $master, CarbonInterface $expiresAt): Master
    {
        $master->update(['access_expires_at' => $expiresAt]);

        return $master->refresh();
    }

    /** Active masters in given city, optionally filtered by category — for order assignment dropdown. */
    public function eligibleForOrder(int $cityId, ?int $categoryId = null): Collection
    {
        return Master::with(['categories', 'latestLocation', 'client'])
            ->where('status', MasterStatus::Approved)
            ->where('city_id', $cityId)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('access_expires_at')
                    ->orWhere('access_expires_at', '>', now());
            })
            ->when($categoryId, fn ($q, $catId) => $q->whereHas(
                'categories',
                fn ($c) => $c->where('categories.id', $catId)
            ))
            ->get();
    }
}
