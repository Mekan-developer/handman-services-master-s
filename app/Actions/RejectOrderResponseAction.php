<?php

namespace App\Actions;

use App\Enums\OrderResponseStatus;
use App\Events\OrderResponseRejected;
use App\Exceptions\OrderException;
use App\Models\OrderMasterResponse;
use App\Repositories\OrderRepository;

/**
 * The client turns down one master's response. The order itself stays open —
 * other pending responses, and future ones, are unaffected.
 */
class RejectOrderResponseAction
{
    public function __construct(private readonly OrderRepository $orderRepository) {}

    /** @throws OrderException */
    public function handle(OrderMasterResponse $response, ?string $reason): OrderMasterResponse
    {
        if ($response->status !== OrderResponseStatus::Pending) {
            throw OrderException::responseNotPending();
        }

        $rejected = $this->orderRepository->rejectResponse($response, $reason);

        OrderResponseRejected::dispatch($rejected);

        return $rejected;
    }
}
