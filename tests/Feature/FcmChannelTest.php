<?php

namespace Tests\Feature;

use App\Enums\OrderResponseStatus;
use App\Enums\OrderStatus;
use App\Models\Client;
use App\Models\ClientDevice;
use App\Models\Master;
use App\Models\MasterSubscription;
use App\Models\Order;
use App\Models\OrderMasterResponse;
use App\Notifications\Push\MasterAssignedNotification;
use App\Notifications\Push\MasterRespondedNotification;
use App\Notifications\Push\NewOrderNearbyNotification;
use App\Notifications\Push\OrderCancelledNotification;
use App\Notifications\Push\OrderStatusChangedNotification;
use App\Notifications\Push\ResponseApprovedNotification;
use App\Notifications\Push\SubscriptionExpiredNotification;
use App\Notifications\Push\SubscriptionExpiringNotification;
use App\Notifications\TestPushNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * FcmChannel: one multicast per account, dead tokens pruned from the report.
 * Firebase itself is mocked — no network, no credentials needed.
 */
class FcmChannelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function mockMessaging(): MockInterface
    {
        $messaging = Mockery::mock(Messaging::class);
        $this->app->instance(Messaging::class, $messaging);

        return $messaging;
    }

    public function test_sends_one_multicast_to_every_device_of_client(): void
    {
        $client = Client::factory()->create();
        ClientDevice::factory()->for($client)->create(['token' => 'token-a']);
        ClientDevice::factory()->for($client)->create(['token' => 'token-b']);

        $this->mockMessaging()
            ->shouldReceive('sendMulticast')
            ->once()
            ->withArgs(function (CloudMessage $message, array $tokens): bool {
                $payload = $message->jsonSerialize();

                return $tokens === ['token-a', 'token-b']
                    && $payload['notification']['title'] === 'Hello'
                    && $payload['data']['type'] === 'test';
            })
            ->andReturn(MulticastSendReport::withItems([
                SendReport::success(MessageTarget::with(MessageTarget::TOKEN, 'token-a'), []),
                SendReport::success(MessageTarget::with(MessageTarget::TOKEN, 'token-b'), []),
            ]));

        $client->notify(new TestPushNotification('Hello', 'World'));

        $this->assertSame(2, $client->devices()->count());
    }

    public function test_prunes_unknown_and_invalid_tokens(): void
    {
        $client = Client::factory()->create();
        ClientDevice::factory()->for($client)->create(['token' => 'alive']);
        ClientDevice::factory()->for($client)->create(['token' => 'uninstalled']);
        ClientDevice::factory()->for($client)->create(['token' => 'garbage']);

        $this->mockMessaging()
            ->shouldReceive('sendMulticast')
            ->once()
            ->andReturn(MulticastSendReport::withItems([
                SendReport::success(MessageTarget::with(MessageTarget::TOKEN, 'alive'), []),
                SendReport::failure(MessageTarget::with(MessageTarget::TOKEN, 'uninstalled'), new NotFound('Requested entity was not found.')),
                SendReport::failure(MessageTarget::with(MessageTarget::TOKEN, 'garbage'), new InvalidMessage('The registration token is not a valid FCM registration token')),
            ]));

        $client->notify(new TestPushNotification('Hello', 'World'));

        $this->assertSame(['ru' => ['alive']], $client->routeNotificationForFcm());
    }

    public function test_renders_message_once_per_app_language(): void
    {
        $client = Client::factory()->create();
        ClientDevice::factory()->for($client)->create(['token' => 'ru-phone', 'locale' => 'ru']);
        ClientDevice::factory()->for($client)->create(['token' => 'tk-phone', 'locale' => 'tk']);

        $order = Order::factory()->for($client)->create();

        $sent = [];

        $this->mockMessaging()
            ->shouldReceive('sendMulticast')
            ->twice()
            ->andReturnUsing(function (CloudMessage $message, array $tokens) use (&$sent): MulticastSendReport {
                $sent[$tokens[0]] = $message->jsonSerialize()['notification']['title'];

                return MulticastSendReport::withItems([]);
            });

        $client->notify(new OrderStatusChangedNotification($order, OrderStatus::Completed));

        $this->assertSame([
            'ru-phone' => trans('push.client.order_completed.title', [], 'ru'),
            'tk-phone' => trans('push.client.order_completed.title', [], 'tk'),
        ], $sent);
        $this->assertNotSame($sent['ru-phone'], $sent['tk-phone']);
    }

    public function test_every_push_is_translated_in_both_languages(): void
    {
        $master = Master::factory()->create();
        $order = Order::factory()->forMaster($master)->assigned()->create();
        $response = OrderMasterResponse::create([
            'order_id' => $order->id,
            'master_id' => $master->id,
            'status' => OrderResponseStatus::Approved,
        ]);
        $subscription = MasterSubscription::factory()->forMaster($master)->create();

        $notifications = [
            new MasterRespondedNotification($response),
            new MasterAssignedNotification($order),
            new OrderStatusChangedNotification($order, OrderStatus::InProgress),
            new OrderStatusChangedNotification($order, OrderStatus::Completed),
            new NewOrderNearbyNotification($order),
            new ResponseApprovedNotification($order),
            new OrderCancelledNotification($order),
            new SubscriptionExpiringNotification($subscription),
            new SubscriptionExpiredNotification($subscription),
        ];

        foreach (['ru', 'tk'] as $locale) {
            app()->setLocale($locale);

            foreach ($notifications as $notification) {
                $payload = $notification->toFcm($master)->jsonSerialize();

                $this->assertStringNotContainsString('push.', $payload['notification']['title'], $notification::class);
                $this->assertStringNotContainsString('push.', $payload['notification']['body'], $notification::class);
                $this->assertDoesNotMatchRegularExpression('/:[a-z]+/', $payload['notification']['body'], $notification::class);
                $this->assertArrayHasKey('type', $payload['data']);
                $this->assertArrayHasKey('audience', $payload['data']);
            }
        }
    }

    public function test_master_pushes_reach_phones_of_its_client_account(): void
    {
        $master = Master::factory()->create();
        ClientDevice::factory()->for($master->client)->create(['token' => 'master-phone']);

        $this->assertSame(['ru' => ['master-phone']], $master->routeNotificationForFcm());
    }

    public function test_skips_firebase_when_client_has_no_devices(): void
    {
        $client = Client::factory()->create();

        $this->mockMessaging()->shouldNotReceive('sendMulticast');

        $client->notify(new TestPushNotification('Hello', 'World'));
    }

    public function test_command_sends_test_push(): void
    {
        $client = Client::factory()->create();
        ClientDevice::factory()->for($client)->create(['token' => 'token-a']);

        $this->mockMessaging()
            ->shouldReceive('sendMulticast')
            ->once()
            ->andReturn(MulticastSendReport::withItems([
                SendReport::success(MessageTarget::with(MessageTarget::TOKEN, 'token-a'), []),
            ]));

        $this->artisan('fcm:test', ['client' => $client->id])
            ->expectsOutput('Sent to 1 device(s); 1 token(s) still valid.')
            ->assertSuccessful();
    }

    public function test_command_fails_for_client_without_devices(): void
    {
        $client = Client::factory()->create();

        $this->mockMessaging()->shouldNotReceive('sendMulticast');

        $this->artisan('fcm:test', ['client' => $client->id])
            ->expectsOutput("Client #{$client->id} has no registered devices.")
            ->assertFailed();
    }
}
