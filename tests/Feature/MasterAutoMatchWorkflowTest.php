<?php

namespace Tests\Feature;

use App\Enums\MasterStatus;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\Master;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Walks the exact flow a manual QA pass would run: two people register
 * through OTP, one becomes a master with a paid one-month subscription, the
 * other posts a request ~10km away, and the auto-search feed must surface
 * that request to the master with no manual assignment involved.
 */
class MasterAutoMatchWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const MASTER_LAT = 37.9601;

    private const MASTER_LNG = 58.3261;

    public function test_client_order_is_automatically_surfaced_to_the_nearby_subscribed_master(): void
    {
        Http::fake(['*/emit-otp' => Http::response(['message' => 'OTP event emitted'])]);

        $city = City::factory()->create();
        $category = Category::factory()->child(Category::factory()->create())->create();
        $plan = SubscriptionPlan::factory()->days(30)->create(['price' => 99]);

        // ── User A registers, then applies to become a master ──────────────────
        $tokenA = $this->registerClient('+99362100001', 'Мастер Ашир', $city->id);

        $application = $this->withToken($tokenA)
            ->postJson(route('api.v1.client.master-application.store'), [
                'city_id' => $city->id,
                'category_ids' => [$category->id],
                'experience_years' => 5,
                'about' => 'Сантехник',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', MasterStatus::Pending->value);

        $master = Master::findOrFail($application->json('data.id'));

        // ── An administrator approves the application with a 1-month tariff ────
        $admin = User::factory()->create();
        $this->actingAs($admin)
            ->post(route('master-applications.approve', $master), [
                'subscription_plan_id' => $plan->id,
                'subscription_note' => 'Оплата наличными за месяц',
            ])
            ->assertRedirect(route('master-applications.index'));

        // The admin session must not leak into the token-authenticated calls below —
        // sanctum's guard checks the stateful session before the bearer token.
        auth()->guard('web')->logout();
        $this->flushSession();

        $master->refresh();
        $this->assertSame(MasterStatus::Approved, $master->status);
        $this->assertTrue($master->hasActiveAccess());
        $this->assertDatabaseHas('master_subscriptions', [
            'master_id' => $master->id,
            'subscription_plan_id' => $plan->id,
            'duration_days' => 30,
        ]);

        // ── Master A comes online and reports their position ────────────────────
        $this->app['auth']->forgetGuards();

        $this->withToken($tokenA)
            ->patchJson(route('api.v1.master.availability.update'), ['is_available' => true])
            ->assertOk()
            ->assertJsonPath('data.is_available', true);

        $this->withToken($tokenA)
            ->postJson(route('api.v1.master.location.store', $master->id), [
                'latitude' => self::MASTER_LAT,
                'longitude' => self::MASTER_LNG,
            ])
            ->assertCreated();

        // ── User B registers and stays a plain client ───────────────────────────
        $this->app['auth']->forgetGuards();
        $tokenB = $this->registerClient('+99362100002', 'Клиент Байрам', $city->id);

        // A point ~10km north of the master — inside the default 20km search radius.
        $clientLat = self::MASTER_LAT + 10 / Order::KM_PER_LAT_DEGREE;

        $orderResponse = $this->withToken($tokenB)
            ->postJson(route('api.v1.client.orders.store'), [
                'city_id' => $city->id,
                'category_id' => $category->id,
                'description' => 'Течёт кран на кухне, нужен мастер сегодня',
                'client_phone' => '+99362100002',
                'client_address' => 'ул. Огузхана 12',
                'client_lat' => $clientLat,
                'client_lng' => self::MASTER_LNG,
            ])
            ->assertCreated();

        $orderId = $orderResponse->json('data.id');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => OrderStatus::Pending->value,
            'master_id' => null,
        ]);

        // ── The system finds master A automatically, with no admin involved ────
        $this->app['auth']->forgetGuards();

        $available = $this->withToken($tokenA)
            ->getJson(route('api.v1.master.orders.available'))
            ->assertOk();

        $available->assertJsonCount(1, 'data');
        $this->assertSame($orderId, $available->json('data.0.id'));
        $this->assertEqualsWithDelta(10.0, $available->json('data.0.distance_km'), 0.3);

        // Master A claims the request the auto-search surfaced.
        $this->withToken($tokenA)
            ->postJson(route('api.v1.master.orders.respond', $orderId))
            ->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => OrderStatus::Assigned->value,
            'master_id' => $master->id,
        ]);
    }

    private function registerClient(string $phone, string $name, int $cityId): string
    {
        $this->postJson(route('api.v1.client.auth.request-otp'), ['phone' => $phone])
            ->assertOk();

        $code = Cache::get("client_otp:{$phone}");

        /** @var TestResponse $verify */
        $verify = $this->postJson(route('api.v1.client.auth.verify-otp'), [
            'phone' => $phone,
            'code' => $code,
        ])->assertOk();

        $token = $verify->json('token');

        $this->withToken($token)
            ->postJson(route('api.v1.client.auth.complete-registration'), [
                'name' => $name,
                'city_id' => $cityId,
            ])
            ->assertOk();

        return $token;
    }
}
