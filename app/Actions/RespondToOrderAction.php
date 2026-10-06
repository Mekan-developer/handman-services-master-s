<?php

namespace App\Actions;

use App\Actions\Concerns\EnsuresMasterEligibility;
use App\Enums\OrderStatus;
use App\Events\MasterRespondedToOrder;
use App\Exceptions\OrderException;
use App\Models\Master;
use App\Models\Order;
use App\Models\OrderMasterResponse;
use App\Repositories\MasterRepository;
use App\Repositories\OrderRepository;

/**
 * A master responds to an auto-search offer. Unlike administrator assignment
 * this does not hand the order over on its own — several masters may respond
 * to the same order, and the client decides who gets it (see
 * ApproveOrderResponseAction).
 */
class RespondToOrderAction
{
    use EnsuresMasterEligibility;

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly MasterRepository $masterRepository,
    ) {}

    /** @throws OrderException */
    public function handle(Master $master, Order $order): OrderMasterResponse
    {
        if ($order->status !== OrderStatus::Pending || $order->master_id !== null) {
            throw OrderException::alreadyClaimed();
        }

        if ($order->search_expired_at !== null) {
            throw OrderException::searchExpired();
        }

        if ($order->client_id === $master->client_id) {
            throw OrderException::ownOrder();
        }

        $this->ensureMasterCanTakeOrders($master);

        if (! in_array($order->category_id, $this->orderRepository->masterCategoryIds($master), true)) {
            throw OrderException::categoryMismatch();
        }

        $location = $this->masterRepository->latestLocation($master);

        if ($location === null) {
            throw OrderException::masterLocationUnknown();
        }

        // Re-check the distance server-side: the feed already filtered by radius,
        // but nothing stops a master from POSTing an order id it never received.
        $distanceKm = $order->distanceKmTo((float) $location->latitude, (float) $location->longitude);

        if ($order->search_radius_km === null || $distanceKm > $order->search_radius_km) {
            throw OrderException::outOfSearchRadius();
        }

        if ($this->orderRepository->hasResponded($order, $master->id)) {
            throw OrderException::alreadyResponded();
        }

        $response = $this->orderRepository->respondToOrder($order, $master);

        MasterRespondedToOrder::dispatch($response->load('master'));

        return $response;
    }
}
