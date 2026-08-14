<?php

namespace App\Repositories;

use App\Enums\SubscriptionStatus;
use App\Models\Master;
use App\Models\MasterSubscription;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class MasterSubscriptionRepository
{
    /**
     * Purchase history, newest first.
     *
     * @param  array{master_id?: int|string, status?: string}  $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return MasterSubscription::with(['master:id,name,phone', 'plan', 'createdBy:id,name'])
            ->when($filters['master_id'] ?? null, fn ($q, $masterId) => $q->where('master_id', $masterId))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Full history of a single master — mobile "my subscriptions" and admin drill-down. */
    public function forMaster(Master $master): Collection
    {
        return MasterSubscription::with('plan')
            ->where('master_id', $master->id)
            ->latest('id')
            ->get();
    }

    public function findOrFail(int $id): MasterSubscription
    {
        return MasterSubscription::findOrFail($id);
    }

    /**
     * The subscription currently granting access — the running one, or the next
     * queued one when nothing is running yet.
     */
    public function currentForMaster(Master $master): ?MasterSubscription
    {
        return MasterSubscription::with('plan')
            ->where('master_id', $master->id)
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Pending->value])
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [SubscriptionStatus::Active->value])
            ->orderBy('starts_at')
            ->first();
    }

    /** The single running subscription, if any — used to enforce "one active at a time". */
    public function activeForMaster(Master $master): ?MasterSubscription
    {
        return MasterSubscription::where('master_id', $master->id)
            ->where('status', SubscriptionStatus::Active->value)
            ->orderByDesc('expires_at')
            ->first();
    }

    /**
     * Furthest paid-for moment across the master's running and queued subscriptions.
     * This is the single source of truth for `masters.access_expires_at`.
     */
    public function accessEndsAtFor(Master $master): ?Carbon
    {
        $value = MasterSubscription::where('master_id', $master->id)
            ->whereIn('status', array_map(
                fn (SubscriptionStatus $status) => $status->value,
                SubscriptionStatus::grantingAccess(),
            ))
            ->max('expires_at');

        return $value === null ? null : Carbon::parse($value);
    }

    /** Active subscriptions whose end date has passed — the expire command's input. */
    public function dueForExpiry(): Collection
    {
        return MasterSubscription::with('master')
            ->where('status', SubscriptionStatus::Active->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();
    }

    /** Queued subscriptions whose start date has arrived. */
    public function dueForActivation(): Collection
    {
        return MasterSubscription::with('master')
            ->where('status', SubscriptionStatus::Pending->value)
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', now())
            ->orderBy('starts_at')
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): MasterSubscription
    {
        return MasterSubscription::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(MasterSubscription $subscription, array $data): MasterSubscription
    {
        $subscription->update($data);

        return $subscription->refresh();
    }

    public function updateStatus(MasterSubscription $subscription, SubscriptionStatus $status): MasterSubscription
    {
        $subscription->update(['status' => $status]);

        return $subscription->refresh();
    }

    public function delete(MasterSubscription $subscription): void
    {
        $subscription->delete();
    }

    /**
     * Headline numbers for the admin page.
     *
     * @return array{active: int, expiring_soon: int, revenue: float}
     */
    public function stats(): array
    {
        return [
            'active' => MasterSubscription::where('status', SubscriptionStatus::Active->value)->count(),
            'expiring_soon' => MasterSubscription::where('status', SubscriptionStatus::Active->value)
                ->whereBetween('expires_at', [now(), now()->addDays(7)])
                ->count(),
            'revenue' => (float) MasterSubscription::whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Pending->value,
                SubscriptionStatus::Expired->value,
            ])->sum('price_paid'),
        ];
    }
}
