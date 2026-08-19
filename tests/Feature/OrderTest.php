<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\Client;
use App\Models\Master;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    private function validPayload(City $city, Category $category): array
    {
        return [
            'city_id' => $city->id,
            'category_id' => $category->id,
            'client_name' => 'Aman Jumayev',
            'client_phone' => '+99362111222',
            'description' => 'Кран течёт уже неделю, нужна срочная починка.',
            'client_address' => 'ул. Андалиб, 12',
            'client_lat' => 37.952321,
            'client_lng' => 58.382345,
        ];
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_orders_index_requires_auth(): void
    {
        $this->get(route('orders.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_view_orders_index(): void
    {
        $this->actingAsAdmin();
        Order::factory()->count(2)->create();

        $this->get(route('orders.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Orders/Index')->has('orders'));
    }

    public function test_orders_index_can_be_filtered_by_status(): void
    {
        $this->actingAsAdmin();
        Order::factory()->create();
        Order::factory()->completed()->create();

        $this->get(route('orders.index', ['status' => OrderStatus::Completed->value]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('orders.data.0.status', 'completed'));
    }

    public function test_orders_index_can_be_searched_by_client_name(): void
    {
        $this->actingAsAdmin();
        Order::factory()->create(['client_name' => 'Aman Jumayev']);
        Order::factory()->create(['client_name' => 'Merdan Saparov']);

        $this->get(route('orders.index', ['search' => 'Jumayev']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('orders.data', fn ($orders) => count($orders) === 1)
                ->where('orders.data.0.client_name', 'Aman Jumayev'));
    }

    public function test_orders_index_can_be_searched_by_client_phone(): void
    {
        $this->actingAsAdmin();
        Order::factory()->create(['client_phone' => '+99362111222']);
        Order::factory()->create(['client_phone' => '+99365999888']);

        $this->get(route('orders.index', ['search' => '111222']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('orders.data', fn ($orders) => count($orders) === 1)
                ->where('orders.data.0.client_phone', '+99362111222'));
    }

    public function test_orders_index_can_be_filtered_by_date_range(): void
    {
        $this->actingAsAdmin();
        Order::factory()->create(['created_at' => '2026-01-10 12:00:00']);
        Order::factory()->create(['created_at' => '2026-03-20 12:00:00']);

        $this->get(route('orders.index', ['date_from' => '2026-03-01', 'date_to' => '2026-03-31']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('orders.data', fn ($orders) => count($orders) === 1));
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_admin_can_view_order_details(): void
    {
        $this->actingAsAdmin();
        $order = Order::factory()->create();

        $this->get(route('orders.show', $order))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Orders/Show')
                ->where('order.id', $order->id));
    }

    public function test_show_returns_404_for_unknown_order(): void
    {
        $this->actingAsAdmin();
        $this->get(route('orders.show', 999))->assertNotFound();
    }

    // ── Restart search ───────────────────────────────────────────────────────

    public function test_admin_can_restart_search_on_a_pending_order(): void
    {
        $this->actingAsAdmin();
        $order = Order::factory()->searching(20, now()->subMinutes(10))->searchExpired()->create();

        $this->post(route('orders.restart-search', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->assertNull($order->fresh()->search_expired_at);
    }

    public function test_admin_cannot_restart_search_on_an_assigned_order(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();
        $order = Order::factory()->forMaster($master)->assigned()->create();
        $previousExpiredAt = $order->search_expired_at;

        $this->post(route('orders.restart-search', $order))
            ->assertRedirect();

        $this->assertEquals($previousExpiredAt, $order->fresh()->search_expired_at);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_admin_can_create_order(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();
        $category = Category::factory()->create();

        $this->post(route('orders.store'), $this->validPayload($city, $category))
            ->assertRedirect(route('orders.index'));

        $this->assertDatabaseHas('orders', [
            'client_name' => 'Aman Jumayev',
            'status' => 'pending',
        ]);
    }

    public function test_admin_can_create_order_for_existing_client(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();
        $category = Category::factory()->create();
        $client = Client::factory()->create();

        $payload = array_merge($this->validPayload($city, $category), [
            'client_id' => $client->id,
        ]);

        $this->post(route('orders.store'), $payload)
            ->assertRedirect(route('orders.index'));

        $this->assertDatabaseHas('orders', [
            'client_id' => $client->id,
            'client_phone' => $client->phone,
            'status' => 'pending',
        ]);
    }

    public function test_creating_order_for_unknown_phone_creates_client(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();
        $category = Category::factory()->create();

        $this->post(route('orders.store'), $this->validPayload($city, $category))
            ->assertRedirect();

        $this->assertDatabaseHas('clients', [
            'phone' => '+99362111222',
            'name' => 'Aman Jumayev',
        ]);

        $client = Client::where('phone', '+99362111222')->firstOrFail();
        $this->assertDatabaseHas('orders', [
            'client_id' => $client->id,
            'client_name' => 'Aman Jumayev',
        ]);
    }

    public function test_creating_order_with_photos_stores_them(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $city = City::factory()->create();
        $category = Category::factory()->create();

        $payload = array_merge($this->validPayload($city, $category), [
            'photos' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
            ],
        ]);

        $this->post(route('orders.store'), $payload)->assertRedirect();

        $order = Order::where('client_name', 'Aman Jumayev')->firstOrFail();
        $this->assertCount(2, $order->photos);
    }

    public function test_store_fails_without_required_fields(): void
    {
        $this->actingAsAdmin();
        $this->post(route('orders.store'), [])
            ->assertSessionHasErrors(['city_id', 'category_id', 'client_name', 'client_phone', 'description', 'client_lat', 'client_lng']);
    }

    public function test_store_rejects_more_than_4_photos(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $city = City::factory()->create();
        $category = Category::factory()->create();

        $payload = array_merge($this->validPayload($city, $category), [
            'photos' => array_fill(0, 5, UploadedFile::fake()->image('p.jpg')),
        ]);

        $this->post(route('orders.store'), $payload)->assertSessionHasErrors('photos');
    }

    // ── Update status ─────────────────────────────────────────────────────────

    public function test_admin_can_transition_assigned_to_in_progress(): void
    {
        $this->actingAsAdmin();
        $order = Order::factory()->assigned()->create();

        $this->post(route('orders.update-status', $order), ['status' => 'in_progress'])
            ->assertRedirect();

        $this->assertEquals('in_progress', $order->fresh()->status->value);
        $this->assertNotNull($order->fresh()->started_at);
    }

    public function test_admin_can_complete_order(): void
    {
        $this->actingAsAdmin();
        $order = Order::factory()->inProgress()->create();

        $this->post(route('orders.update-status', $order), ['status' => 'completed'])
            ->assertRedirect();

        $this->assertEquals('completed', $order->fresh()->status->value);
        $this->assertNotNull($order->fresh()->completed_at);
    }

    public function test_completing_order_without_final_price_warns(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();
        $order = Order::factory()->forMaster($master)->inProgress()->create(['final_price' => null]);

        $this->post(route('orders.update-status', $order), ['status' => 'completed'])
            ->assertRedirect()
            ->assertSessionHas('notification', fn ($notification) => $notification['type'] === 'warning');

        $this->assertEquals('completed', $order->fresh()->status->value);
    }

    public function test_completing_order_with_final_price_reports_success(): void
    {
        $this->actingAsAdmin();
        $master = Master::factory()->create();
        $order = Order::factory()->forMaster($master)->inProgress()->create(['final_price' => 1000]);

        $this->post(route('orders.update-status', $order), ['status' => 'completed'])
            ->assertRedirect()
            ->assertSessionHas('notification', fn ($notification) => $notification['type'] === 'success');

        $this->assertEquals('completed', $order->fresh()->status->value);
    }

    public function test_admin_can_cancel_order_with_reason(): void
    {
        $this->actingAsAdmin();
        $order = Order::factory()->create();

        $this->post(route('orders.update-status', $order), [
            'status' => 'cancelled',
            'cancel_reason' => 'Клиент передумал',
        ])->assertRedirect();

        $fresh = $order->fresh();
        $this->assertEquals('cancelled', $fresh->status->value);
        $this->assertEquals('Клиент передумал', $fresh->cancel_reason);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $this->actingAsAdmin();
        $order = Order::factory()->create();

        $this->post(route('orders.update-status', $order), ['status' => 'completed'])
            ->assertRedirect();

        $this->assertEquals('pending', $order->fresh()->status->value);
    }

    public function test_pending_order_cannot_be_manually_set_to_assigned_without_a_master(): void
    {
        $this->actingAsAdmin();
        $order = Order::factory()->create();

        $this->post(route('orders.update-status', $order), ['status' => 'assigned'])
            ->assertRedirect();

        $fresh = $order->fresh();
        $this->assertEquals('pending', $fresh->status->value);
        $this->assertNull($fresh->master_id);
    }

    // ── Update ───────────────────────────────────────────────────────────────

    public function test_admin_can_update_pending_order(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();
        $category = Category::factory()->create();
        $order = Order::factory()->create(['status' => 'pending']);

        $this->put(route('orders.update', $order), [
            'city_id' => $city->id,
            'category_id' => $category->id,
            'client_name' => 'Обновлённое имя',
            'client_phone' => '+99362999888',
            'description' => 'Новое описание проблемы',
            'client_address' => 'ул. Новая, 5',
            'client_lat' => 37.95,
            'client_lng' => 58.38,
        ])->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'client_name' => 'Обновлённое имя',
            'city_id' => $city->id,
        ]);
    }

    public function test_update_fails_on_assigned_order(): void
    {
        $this->actingAsAdmin();
        $city = City::factory()->create();
        $category = Category::factory()->create();
        $order = Order::factory()->assigned()->create();

        $this->put(route('orders.update', $order), [
            'city_id' => $city->id,
            'category_id' => $category->id,
            'client_name' => 'Test',
            'client_phone' => '+99362000000',
            'description' => 'Test',
            'client_lat' => 37.95,
            'client_lng' => 58.38,
        ])->assertRedirect();

        $this->assertNotEquals('Test', $order->fresh()->client_name);
    }

    public function test_update_fails_without_required_fields(): void
    {
        $this->actingAsAdmin();
        $order = Order::factory()->create(['status' => 'pending']);

        $this->put(route('orders.update', $order), [])
            ->assertSessionHasErrors(['city_id', 'category_id', 'client_name', 'client_phone', 'description', 'client_lat', 'client_lng']);
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function test_admin_can_delete_order(): void
    {
        $this->actingAsAdmin();
        $order = Order::factory()->create();

        $this->delete(route('orders.destroy', $order))
            ->assertRedirect(route('orders.index'));

        $this->assertModelMissing($order);
    }
}
