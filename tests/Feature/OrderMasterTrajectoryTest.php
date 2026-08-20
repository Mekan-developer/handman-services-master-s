<?php

namespace Tests\Feature;

use App\Models\Master;
use App\Models\MasterLocation;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OrderMasterTrajectoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function ping(Master $master, ?Order $order, float $latitude, float $longitude, string $recordedAt): MasterLocation
    {
        return MasterLocation::factory()->create([
            'master_id' => $master->id,
            'order_id' => $order?->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'recorded_at' => $recordedAt,
        ]);
    }

    public function test_trajectory_requires_auth(): void
    {
        $order = Order::factory()->inProgress()->create();

        $this->get(route('orders.master-trajectory', $order->id))->assertRedirect(route('login'));
    }

    public function test_trajectory_returns_the_orders_own_pings_oldest_first(): void
    {
        $this->actingAs(User::factory()->create());

        $master = Master::factory()->create();
        $order = Order::factory()->forMaster($master)->inProgress()->create();

        $this->ping($master, $order, 37.95, 58.38, '2026-08-20 10:02:00');
        $this->ping($master, $order, 37.96, 58.39, '2026-08-20 10:00:00');
        $this->ping($master, $order, 37.97, 58.40, '2026-08-20 10:01:00');

        $this->getJson(route('orders.master-trajectory', $order->id))
            ->assertOk()
            ->assertJsonCount(3, 'points')
            ->assertJsonPath('points.0.latitude', 37.96)
            ->assertJsonPath('points.1.latitude', 37.97)
            ->assertJsonPath('points.2.latitude', 37.95);
    }

    /**
     * The trail is what the master drove for *this* job. Pings from their other
     * work, and the untagged wandering in between, used to be folded in by a
     * bounding box around the client and drawn as one nonsensical line.
     */
    public function test_trajectory_excludes_pings_from_other_orders_and_untagged_pings(): void
    {
        $this->actingAs(User::factory()->create());

        $master = Master::factory()->create();
        $order = Order::factory()->forMaster($master)->inProgress()->create();
        $otherOrder = Order::factory()->forMaster($master)->completed()->create();

        $this->ping($master, $order, 37.95, 58.38, '2026-08-20 10:00:00');
        $this->ping($master, $otherOrder, 37.96, 58.39, '2026-08-20 10:01:00');
        $this->ping($master, null, 37.97, 58.40, '2026-08-20 10:02:00');

        $this->getJson(route('orders.master-trajectory', $order->id))
            ->assertOk()
            ->assertJsonCount(1, 'points')
            ->assertJsonPath('points.0.latitude', 37.95);
    }

    public function test_trajectory_is_empty_for_an_order_without_a_master(): void
    {
        $this->actingAs(User::factory()->create());

        $order = Order::factory()->create();

        $this->getJson(route('orders.master-trajectory', $order->id))
            ->assertOk()
            ->assertExactJson(['points' => []]);
    }

    public function test_trajectory_404s_for_an_unknown_order(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson(route('orders.master-trajectory', 999999))->assertNotFound();
    }
}
