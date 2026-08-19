<?php

namespace Tests\Feature\Api\V1;

use App\Enums\OrderResponseStatus;
use App\Enums\OrderStatus;
use App\Events\MasterAssigned;
use App\Events\OrderResponseSuperseded;
use App\Models\Client;
use App\Models\Master;
use App\Models\Order;
use App\Models\OrderMasterResponse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientApproveOrderResponseTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function actingAsClient(): Client
    {
        $client = Client::factory()->create();
        Sanctum::actingAs($client, ['*']);

        return $client;
    }

    private function approveRoute(Order $order, OrderMasterResponse $response): string
    {
        return route('api.v1.client.orders.responses.approve', ['order' => $order->id, 'responseId' => $response->id]);
    }

    public function test_client_can_approve_a_response(): void
    {
        Event::fake([MasterAssigned::class]);

        $client = $this->actingAsClient();
        $order = Order::factory()->create(['client_id' => $client->id]);
        $master = Master::factory()->create();
        $response = OrderMasterResponse::create([
            'order_id' => $order->id,
            'master_id' => $master->id,
            'status' => OrderResponseStatus::Pending,
        ]);

        $this->postJson($this->approveRoute($order, $response))
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Assigned->value);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Assigned->value,
            'master_id' => $master->id,
        ]);
        $this->assertSame(OrderResponseStatus::Approved, $response->fresh()->status);
        $this->assertNotNull($response->fresh()->decided_at);

        Event::assertDispatched(MasterAssigned::class);
    }

    public function test_approving_one_response_supersedes_the_others(): void
    {
        Event::fake([OrderResponseSuperseded::class]);

        $client = $this->actingAsClient();
        $order = Order::factory()->create(['client_id' => $client->id]);

        $winner = OrderMasterResponse::create([
            'order_id' => $order->id,
            'master_id' => Master::factory()->create()->id,
            'status' => OrderResponseStatus::Pending,
        ]);
        $loser = OrderMasterResponse::create([
            'order_id' => $order->id,
            'master_id' => Master::factory()->create()->id,
            'status' => OrderResponseStatus::Pending,
        ]);

        $this->postJson($this->approveRoute($order, $winner))->assertOk();

        $freshLoser = $loser->fresh();
        $this->assertSame(OrderResponseStatus::Rejected, $freshLoser->status);
        $this->assertNull($freshLoser->rejection_reason);
        $this->assertNotNull($freshLoser->decided_at);

        Event::assertDispatched(
            OrderResponseSuperseded::class,
            fn (OrderResponseSuperseded $event) => $event->response->id === $loser->id
        );
    }

    public function test_cannot_approve_an_already_decided_response(): void
    {
        $client = $this->actingAsClient();
        $order = Order::factory()->create(['client_id' => $client->id]);
        $response = OrderMasterResponse::create([
            'order_id' => $order->id,
            'master_id' => Master::factory()->create()->id,
            'status' => OrderResponseStatus::Rejected,
            'decided_at' => now(),
        ]);

        $this->postJson($this->approveRoute($order, $response))
            ->assertStatus(422)
            ->assertJsonPath('message', __('orders.errors.response_not_pending'));
    }

    public function test_cannot_approve_a_response_on_an_already_assigned_order(): void
    {
        $client = $this->actingAsClient();
        $order = Order::factory()->assigned()->create(['client_id' => $client->id]);
        $response = OrderMasterResponse::create([
            'order_id' => $order->id,
            'master_id' => Master::factory()->create()->id,
            'status' => OrderResponseStatus::Pending,
        ]);

        $this->postJson($this->approveRoute($order, $response))
            ->assertStatus(422)
            ->assertJsonPath('message', __('orders.errors.already_claimed'));
    }

    public function test_client_cannot_approve_a_response_on_another_clients_order(): void
    {
        $this->actingAsClient();
        $order = Order::factory()->create(['client_id' => Client::factory()->create()->id]);
        $response = OrderMasterResponse::create([
            'order_id' => $order->id,
            'master_id' => Master::factory()->create()->id,
            'status' => OrderResponseStatus::Pending,
        ]);

        $this->postJson($this->approveRoute($order, $response))->assertNotFound();
    }

    public function test_approve_requires_authentication(): void
    {
        $order = Order::factory()->create();
        $response = OrderMasterResponse::create([
            'order_id' => $order->id,
            'master_id' => Master::factory()->create()->id,
            'status' => OrderResponseStatus::Pending,
        ]);

        $this->postJson($this->approveRoute($order, $response))->assertUnauthorized();
    }
}
