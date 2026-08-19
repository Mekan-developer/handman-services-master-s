<?php

namespace App\Repositories;

use App\Enums\OrderResponseStatus;
use App\Enums\OrderStatus;
use App\Models\Client;
use App\Models\Master;
use App\Models\Order;
use App\Models\OrderMasterDecline;
use App\Models\OrderMasterResponse;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderRepository
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Order::with(['city', 'category', 'master'])
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['city_id'] ?? null, fn ($q, $cityId) => $q->where('city_id', $cityId))
            ->when($filters['master_id'] ?? null, fn ($q, $masterId) => $q->where('master_id', $masterId))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(
                fn ($sub) => $sub->where('client_name', 'like', "%{$search}%")
                    ->orWhere('client_phone', 'like', "%{$search}%")
            ))
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function forClient(Client $client, ?string $status = null): LengthAwarePaginator
    {
        return Order::with(['category', 'city', 'master.latestLocation', 'review'])
            ->where('client_id', $client->id)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function findForClientOrFail(int $orderId, Client $client): Order
    {
        return Order::with(['category', 'city', 'master.latestLocation', 'photos', 'tasks', 'review'])
            ->where('client_id', $client->id)
            ->findOrFail($orderId);
    }

    public function forMaster(Master $master, ?string $filter = null): LengthAwarePaginator
    {
        // Orders this master responded to are not theirs yet (master_id is still
        // null until the client approves), so they live behind a different query
        // than the master_id-scoped filters below.
        if ($filter === 'awaiting_response') {
            return Order::with(['category'])
                ->whereHas('masterResponses', fn ($q) => $q->where('master_id', $master->id)
                    ->where('status', OrderResponseStatus::Pending))
                ->latest()
                ->paginate(15)
                ->withQueryString();
        }

        return Order::with(['category'])
            ->where('master_id', $master->id)
            ->when($filter === 'active', fn ($q) => $q->whereIn('status', [OrderStatus::Assigned->value, OrderStatus::InProgress->value]))
            ->when($filter === 'history', fn ($q) => $q->whereIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value]))
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function findForMasterOrFail(int $orderId, Master $master): Order
    {
        return Order::with(['category', 'photos', 'tasks.beforePhotos', 'tasks.afterPhotos'])
            ->where('master_id', $master->id)
            ->findOrFail($orderId);
    }

    public function findOrFail(int $id): Order
    {
        return Order::with([
            'city',
            'category',
            'master.latestLocation',
            'photos',
            'tasks.beforePhotos',
            'tasks.afterPhotos',
        ])->findOrFail($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Order
    {
        return Order::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Order $order, array $data): Order
    {
        $order->update($data);

        return $order->fresh();
    }

    public function delete(Order $order): void
    {
        $order->delete();
    }

    public function assignMaster(Order $order, int $masterId, ?string $changeReason = null): Order
    {
        $order->update([
            'master_id' => $masterId,
            'status' => OrderStatus::Assigned,
            'assigned_at' => now(),
            'master_change_reason' => $changeReason,
        ]);

        return $order->fresh();
    }

    /**
     * Orders whose auto-search is still running, streamed in chunks for the
     * every-minute scheduler sweep.
     *
     * @param  callable(EloquentCollection<int, Order>): mixed  $callback
     */
    public function eachPendingSearchable(callable $callback, int $chunkSize = 200): void
    {
        Order::query()
            ->where('status', OrderStatus::Pending)
            ->whereNull('master_id')
            ->whereNotNull('search_started_at')
            ->whereNull('search_expired_at')
            ->chunkById($chunkSize, $callback);
    }

    /**
     * Orders still unassigned well past the response deadline, streamed in
     * chunks for the hourly auto-cancel sweep.
     *
     * @param  callable(EloquentCollection<int, Order>): mixed  $callback
     */
    public function eachStaleUnassigned(callable $callback, int $hours, int $chunkSize = 200): void
    {
        Order::query()
            ->where('status', OrderStatus::Pending)
            ->whereNull('master_id')
            ->where('created_at', '<=', now()->subHours($hours))
            ->chunkById($chunkSize, $callback);
    }

    /**
     * Pending, unclaimed orders in the master's categories that currently sit
     * inside their own search radius, nearest first.
     *
     * The SQL pass is a plain-arithmetic bounding box (no trigonometry, so it
     * behaves identically on MySQL, PostgreSQL and SQLite); the small candidate
     * set is then narrowed to a true circle with Order::distanceKmTo().
     *
     * @return Collection<int, Order>
     */
    public function availableForMaster(Master $master, float $latitude, float $longitude): Collection
    {
        // Degrees of longitude per kilometre shrink towards the poles; the master's
        // latitude is a bound value, so the cosine is safe to resolve here in PHP.
        $lngDegreesPerKm = 1 / (Order::KM_PER_LAT_DEGREE * max(cos(deg2rad($latitude)), 0.01));

        $candidates = Order::with(['category', 'city', 'photos'])
            ->where('status', OrderStatus::Pending)
            ->whereNull('master_id')
            ->whereNotNull('search_started_at')
            ->whereNull('search_expired_at')
            ->whereIn('category_id', $this->masterCategoryIds($master))
            ->whereRaw('abs(client_lat - ?) <= (search_radius_km / ?)', [$latitude, Order::KM_PER_LAT_DEGREE])
            ->whereRaw('abs(client_lng - ?) <= (search_radius_km * ?)', [$longitude, $lngDegreesPerKm])
            ->whereDoesntHave('declines', fn ($q) => $q->where('master_id', $master->id))
            ->whereDoesntHave('masterResponses', fn ($q) => $q->where('master_id', $master->id))
            ->get();

        return $candidates
            ->each(fn (Order $order) => $order->distance_km = round($order->distanceKmTo($latitude, $longitude), 2))
            ->filter(fn (Order $order) => $order->distance_km <= $order->search_radius_km)
            ->sortBy('distance_km')
            ->values();
    }

    public function hasResponded(Order $order, int $masterId): bool
    {
        return OrderMasterResponse::where('order_id', $order->id)
            ->where('master_id', $masterId)
            ->exists();
    }

    public function respondToOrder(Order $order, Master $master): OrderMasterResponse
    {
        return OrderMasterResponse::create([
            'order_id' => $order->id,
            'master_id' => $master->id,
            'status' => OrderResponseStatus::Pending,
        ]);
    }

    /**
     * Pending responses for the client to decide on, nearest master first.
     *
     * @return Collection<int, OrderMasterResponse>
     */
    public function pendingResponsesFor(Order $order): Collection
    {
        $responses = OrderMasterResponse::with(['master.latestLocation'])
            ->where('order_id', $order->id)
            ->where('status', OrderResponseStatus::Pending)
            ->get();

        return $responses
            ->each(function (OrderMasterResponse $response) use ($order): void {
                $location = $response->master->latestLocation;

                $response->distance_km = $location
                    ? round($order->distanceKmTo((float) $location->latitude, (float) $location->longitude), 2)
                    : null;
            })
            ->sortBy('distance_km')
            ->values();
    }

    public function findResponseOrFail(Order $order, int $responseId): OrderMasterResponse
    {
        return OrderMasterResponse::with('master')
            ->where('order_id', $order->id)
            ->findOrFail($responseId);
    }

    /**
     * The client picks a winner: the order is assigned to them, and every other
     * still-pending response on the order is auto-rejected — those masters lost
     * the order to someone else, not to the client explicitly declining them.
     *
     * @return array{0: Order, 1: Collection<int, OrderMasterResponse>}
     */
    public function approveResponse(Order $order, OrderMasterResponse $response): array
    {
        return DB::transaction(function () use ($order, $response) {
            $assigned = $this->assignMaster($order, $response->master_id);

            $response->update(['status' => OrderResponseStatus::Approved, 'decided_at' => now()]);

            $superseded = OrderMasterResponse::where('order_id', $order->id)
                ->where('id', '!=', $response->id)
                ->where('status', OrderResponseStatus::Pending)
                ->get();

            OrderMasterResponse::where('order_id', $order->id)
                ->where('id', '!=', $response->id)
                ->where('status', OrderResponseStatus::Pending)
                ->update(['status' => OrderResponseStatus::Rejected, 'decided_at' => now()]);

            return [$assigned, $superseded];
        });
    }

    public function rejectResponse(OrderMasterResponse $response, ?string $reason): OrderMasterResponse
    {
        $response->update([
            'status' => OrderResponseStatus::Rejected,
            'rejection_reason' => $reason,
            'decided_at' => now(),
        ]);

        return $response->fresh();
    }

    /**
     * The order left the pool while responses were still pending (cancelled,
     * either by the client or by the stale-order sweep) — release them so the
     * masters who responded find out it's no longer up for grabs.
     *
     * @return Collection<int, OrderMasterResponse>
     */
    public function withdrawPendingResponses(Order $order): Collection
    {
        $pending = OrderMasterResponse::where('order_id', $order->id)
            ->where('status', OrderResponseStatus::Pending)
            ->get();

        if ($pending->isNotEmpty()) {
            OrderMasterResponse::where('order_id', $order->id)
                ->where('status', OrderResponseStatus::Pending)
                ->update(['status' => OrderResponseStatus::Rejected, 'decided_at' => now()]);
        }

        return $pending;
    }

    public function expandRadius(Order $order, int $radiusKm): Order
    {
        $order->update(['search_radius_km' => $radiusKm]);

        return $order->fresh();
    }

    public function markSearchExpired(Order $order): Order
    {
        $order->update(['search_expired_at' => now()]);

        return $order->fresh();
    }

    /** Hide the order from this master's feed for good; other masters still see it. */
    public function declineForMaster(Order $order, int $masterId): void
    {
        OrderMasterDecline::firstOrCreate([
            'order_id' => $order->id,
            'master_id' => $masterId,
        ]);
    }

    /** @return array<int, int> */
    public function masterCategoryIds(Master $master): array
    {
        return $master->categories()->pluck('categories.id')->all();
    }

    public function changeStatus(Order $order, OrderStatus $status): Order
    {
        $payload = ['status' => $status];

        match ($status) {
            OrderStatus::InProgress => $payload['started_at'] = now(),
            OrderStatus::Completed => $payload['completed_at'] = now(),
            OrderStatus::Cancelled => $payload['cancelled_at'] = now(),
            default => null,
        };

        $order->update($payload);

        return $order->fresh();
    }
}
