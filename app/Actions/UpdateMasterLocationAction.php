<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Events\MasterLocationUpdated;
use App\Exceptions\OrderException;
use App\Models\Master;
use App\Models\MasterLocation;
use App\Repositories\MasterLocationRepository;
use App\Repositories\OrderRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UpdateMasterLocationAction
{
    public function __construct(
        private readonly MasterLocationRepository $locations,
        private readonly OrderRepository $orders,
    ) {}

    /**
     * Record a GPS ping for a master.
     *
     * A ping tagged with an order is what drives the client's live map, so the
     * tag is verified rather than trusted: `exists:orders,id` alone would let a
     * master attach somebody else's order id and broadcast their position into
     * a stranger's private channel. The order must be theirs and still
     * trackable — see {@see OrderStatus::isTrackable()}.
     *
     * An untagged ping is tagged here instead of being stored bare. Everything
     * that draws a trail — the client's live map, the admin order map — reads
     * `order_id`, so a ping the phone forgot to label is a hole in the line the
     * master is currently drawing. The server knows which job they are on; it
     * does not need the phone to say so.
     *
     * @param  array{latitude: float, longitude: float, order_id?: int|null, recorded_at?: string|null}  $data
     *
     * @throws ModelNotFoundException when the order is not this master's
     * @throws OrderException when the order is already finished or cancelled
     */
    public function handle(Master $master, array $data): MasterLocation
    {
        $orderId = $data['order_id'] ?? null;

        if ($orderId !== null) {
            $order = $this->orders->findForMasterOrFail((int) $orderId, $master);

            if (! $order->status->isTrackable()) {
                throw OrderException::orderNotTrackable();
            }
        } else {
            $data['order_id'] = $this->orders->singleTrackableForMaster($master)?->id;
        }

        $location = $this->locations->create($master, $data);

        // The listeners read the order and the master off the ping to decide the
        // channels and the distance — load them once here instead of firing a
        // query per subscriber on every ping.
        MasterLocationUpdated::dispatch($location->load(['master', 'order']));

        return $location;
    }
}
