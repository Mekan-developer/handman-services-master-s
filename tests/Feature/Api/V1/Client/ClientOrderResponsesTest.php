<?php

namespace Tests\Feature\Api\V1\Client;

use App\Enums\OrderResponseStatus;
use App\Models\Client;
use App\Models\Master;
use App\Models\MasterLocation;
use App\Models\Order;
use App\Models\OrderMasterResponse;
use App\Models\OrderReview;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientOrderResponsesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function actingAsClient(): Client
    {
        $client = Client::factory()->create();
        Sanctum::actingAs($client, ['*']);

        return $client;
    }

    private function listRoute(Order $order): string
    {
        return route('api.v1.client.orders.responses', ['order' => $order->id]);
    }

    private function respond(Order $order, Master $master, OrderResponseStatus $status = OrderResponseStatus::Pending): OrderMasterResponse
    {
        return OrderMasterResponse::create([
            'order_id' => $order->id,
            'master_id' => $master->id,
            'status' => $status,
            'decided_at' => $status === OrderResponseStatus::Pending ? null : now(),
        ]);
    }

    public function test_response_card_carries_the_masters_avatar_and_average_rating(): void
    {
        $client = $this->actingAsClient();
        $order = Order::factory()->at(38.0, 58.4)->create(['client_id' => $client->id]);

        $masterAccount = Client::factory()->create(['photo' => 'clients/avatar.jpg']);
        $master = Master::factory()->forClient($masterAccount)->create(['experience_years' => 7]);

        MasterLocation::factory()->create([
            'master_id' => $master->id,
            'latitude' => 38.02,
            'longitude' => 58.4,
            'recorded_at' => now(),
        ]);

        // 4 and 5 → 4.5 average.
        OrderReview::factory()->create(['master_id' => $master->id, 'client_id' => $client->id, 'rating' => 4]);
        OrderReview::factory()->create(['master_id' => $master->id, 'client_id' => $client->id, 'rating' => 5]);

        $this->respond($order, $master);

        $this->getJson($this->listRoute($order))
            ->assertOk()
            ->assertJsonPath('data.0.master.id', $master->id)
            ->assertJsonPath('data.0.master.experience_years', 7)
            ->assertJsonPath('data.0.master.distance_km', 2.22)
            ->assertJsonPath('data.0.master.avatar_url', asset('storage/clients/avatar.jpg'))
            ->assertJsonPath('data.0.master.rating', 4.5);
    }

    public function test_master_without_avatar_reviews_or_location_returns_nulls(): void
    {
        $client = $this->actingAsClient();
        $order = Order::factory()->create(['client_id' => $client->id]);

        $master = Master::factory()->forClient(Client::factory()->create(['photo' => null]))->create();

        $this->respond($order, $master);

        $this->getJson($this->listRoute($order))
            ->assertOk()
            ->assertJsonPath('data.0.master.avatar_url', null)
            ->assertJsonPath('data.0.master.rating', null)
            ->assertJsonPath('data.0.master.distance_km', null);
    }

    public function test_only_pending_responses_are_listed_nearest_first(): void
    {
        $client = $this->actingAsClient();
        $order = Order::factory()->at(38.0, 58.4)->create(['client_id' => $client->id]);

        $far = Master::factory()->create();
        MasterLocation::factory()->create([
            'master_id' => $far->id,
            'latitude' => 38.1,
            'longitude' => 58.4,
            'recorded_at' => now(),
        ]);

        $near = Master::factory()->create();
        MasterLocation::factory()->create([
            'master_id' => $near->id,
            'latitude' => 38.01,
            'longitude' => 58.4,
            'recorded_at' => now(),
        ]);

        $this->respond($order, $far);
        $this->respond($order, $near);
        $this->respond($order, Master::factory()->create(), OrderResponseStatus::Rejected);

        $this->getJson($this->listRoute($order))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.master.id', $near->id)
            ->assertJsonPath('data.1.master.id', $far->id);
    }

    public function test_client_cannot_list_responses_on_another_clients_order(): void
    {
        $this->actingAsClient();
        $order = Order::factory()->create(['client_id' => Client::factory()->create()->id]);

        $this->getJson($this->listRoute($order))->assertNotFound();
    }

    public function test_listing_responses_requires_authentication(): void
    {
        $order = Order::factory()->create();

        $this->getJson($this->listRoute($order))->assertUnauthorized();
    }
}
