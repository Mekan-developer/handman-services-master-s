<?php

namespace Tests\Feature\Api\V1;

use App\Enums\OrderResponseStatus;
use App\Enums\OrderStatus;
use App\Events\OrderResponseRejected;
use App\Models\Client;
use App\Models\Master;
use App\Models\Order;
use App\Models\OrderMasterResponse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientRejectOrderResponseTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function actingAsClient(): Client
    {
        $client = Client::factory()->create();
        Sanctum::actingAs($client, ['*']);

        return $client;
    }

    private function rejectRoute(Order $order, OrderMasterResponse $response): string
    {
        return route('api.v1.client.orders.responses.reject', ['order' => $order->id, 'responseId' => $response->id]);
    }

    private function pendingResponse(Order $order): OrderMasterResponse
    {
        return OrderMasterResponse::create([
            'order_id' => $order->id,
            'master_id' => Master::factory()->create()->id,
            'status' => OrderResponseStatus::Pending,
        ]);
    }

    public function test_client_can_reject_a_response_with_a_reason(): void
    {
        Event::fake([OrderResponseRejected::class]);

        $client = $this->actingAsClient();
        $order = Order::factory()->create(['client_id' => $client->id]);
        $response = $this->pendingResponse($order);

        $this->postJson($this->rejectRoute($order, $response), ['reason' => 'Слишком далеко'])
            ->assertOk()
            ->assertJsonPath('data.status', OrderResponseStatus::Rejected->value)
            ->assertJsonPath('data.rejection_reason', 'Слишком далеко');

        $fresh = $response->fresh();
        $this->assertSame(OrderResponseStatus::Rejected, $fresh->status);
        $this->assertSame('Слишком далеко', $fresh->rejection_reason);
        $this->assertNotNull($fresh->decided_at);

        // The order itself stays open for the remaining/future masters.
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);

        Event::assertDispatched(
            OrderResponseRejected::class,
            fn (OrderResponseRejected $event) => $event->response->id === $response->id
        );
    }

    public function test_reason_is_optional(): void
    {
        $client = $this->actingAsClient();
        $order = Order::factory()->create(['client_id' => $client->id]);
        $response = $this->pendingResponse($order);

        $this->postJson($this->rejectRoute($order, $response))
            ->assertOk()
            ->assertJsonPath('data.status', OrderResponseStatus::Rejected->value)
            ->assertJsonPath('data.rejection_reason', null);
    }

    public function test_a_rejected_masters_response_cannot_be_re_submitted(): void
    {
        $client = $this->actingAsClient();
        $order = Order::factory()->create(['client_id' => $client->id]);
        $response = $this->pendingResponse($order);

        $this->postJson($this->rejectRoute($order, $response))->assertOk();

        $this->assertSame(1, OrderMasterResponse::where('order_id', $order->id)
            ->where('master_id', $response->master_id)
            ->count());
    }

    public function test_cannot_reject_an_already_decided_response(): void
    {
        $client = $this->actingAsClient();
        $order = Order::factory()->create(['client_id' => $client->id]);
        $response = OrderMasterResponse::create([
            'order_id' => $order->id,
            'master_id' => Master::factory()->create()->id,
            'status' => OrderResponseStatus::Approved,
            'decided_at' => now(),
        ]);

        $this->postJson($this->rejectRoute($order, $response))
            ->assertStatus(422)
            ->assertJsonPath('message', __('orders.errors.response_not_pending'));
    }

    public function test_client_cannot_reject_a_response_on_another_clients_order(): void
    {
        $this->actingAsClient();
        $order = Order::factory()->create(['client_id' => Client::factory()->create()->id]);
        $response = $this->pendingResponse($order);

        $this->postJson($this->rejectRoute($order, $response))->assertNotFound();
    }

    public function test_reject_requires_authentication(): void
    {
        $order = Order::factory()->create();
        $response = $this->pendingResponse($order);

        $this->postJson($this->rejectRoute($order, $response))->assertUnauthorized();
    }
}
