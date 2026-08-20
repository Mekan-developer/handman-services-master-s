<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Client\ShowOrderTrackRequest;
use App\Http\Resources\Api\V1\Client\OrderTrackResource;
use App\Models\Client;
use App\Repositories\MasterLocationRepository;
use App\Repositories\OrderRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ClientOrderTrackingController extends Controller
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly MasterLocationRepository $locations,
    ) {}

    /**
     * The master's trail for one of the caller's own orders.
     *
     * Sits alongside the `master.location.updated` broadcast rather than
     * replacing it: the socket delivers new points live, this endpoint fills in
     * what came before — on first open and after a reconnection, where `since`
     * fetches only the missing tail.
     *
     * A finished order answers `is_active: false` with an empty trail instead of
     * an error: "tracking is over" is a normal screen state, not a failure, and
     * the app needs a plain answer to tear the map down.
     */
    public function show(ShowOrderTrackRequest $request, int $id): OrderTrackResource
    {
        /** @var Client $client */
        $client = $request->user();

        $order = $this->orders->findForClientOrFail($id, $client);

        if (! $order->status->isTrackable()) {
            return new OrderTrackResource($order, new Collection);
        }

        $since = $request->validated('since');

        return new OrderTrackResource($order, $this->locations->trackForOrder(
            $order,
            $since === null ? null : Carbon::parse($since),
        ));
    }
}
