<?php

namespace App\Events;

use App\Models\MasterLocation;
use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MasterLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MasterLocation $location) {}

    /**
     * The city-wide channel always gets the ping — it feeds the admin map. It is
     * private: staff only, see routes/channels.php.
     *
     * The client's private channel is added only while the ping belongs to an
     * order that client is waiting on and that order is still trackable. That
     * condition is the stop switch: once the job is completed or cancelled the
     * channel drops off this list and the master stops appearing on the
     * client's map without anything having to be unsubscribed.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('masters-map.'.$this->location->master->city_id)];

        $order = $this->trackedOrder();

        if ($order !== null && $order->client_id !== null) {
            $channels[] = new PrivateChannel('client.'.$order->client_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'master.location.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'master_id' => $this->location->master_id,
            'order_id' => $this->location->order_id,
            'latitude' => (float) $this->location->latitude,
            'longitude' => (float) $this->location->longitude,
            'distance_km' => $this->distanceToClient(),
            'recorded_at' => $this->location->recorded_at->toIso8601String(),
        ];
    }

    /** The order this ping is being tracked for, or null when it is a plain position report. */
    private function trackedOrder(): ?Order
    {
        $order = $this->location->order;

        return $order !== null && $order->status->isTrackable() ? $order : null;
    }

    /**
     * How far the master still is from the client's address, in kilometres.
     * Straight-line distance, not road distance — enough to answer "is he
     * close yet?", which is all the client app shows. Null on pings that carry
     * no order.
     */
    private function distanceToClient(): ?float
    {
        $order = $this->trackedOrder();

        if ($order === null) {
            return null;
        }

        return round($order->distanceKmTo(
            (float) $this->location->latitude,
            (float) $this->location->longitude,
        ), 2);
    }
}
