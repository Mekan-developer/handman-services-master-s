<?php

namespace Tests\Feature;

use App\Events\OrderCreated;
use App\Listeners\NotifyAdminsOnNewOrder;
use App\Models\Order;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use App\Repositories\UserRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotifyAdminsOnNewOrderTest extends TestCase
{
    use RefreshDatabase;

    private function fireForNewOrder(): Order
    {
        $order = Order::factory()->create();

        app(NotifyAdminsOnNewOrder::class)->handle(new OrderCreated($order));

        return $order;
    }

    public function test_administrators_and_managers_are_notified(): void
    {
        Notification::fake();

        $administrator = User::factory()->administrator()->create();
        $manager = User::factory()->manager()->create();

        $order = $this->fireForNewOrder();

        foreach ([$administrator, $manager] as $recipient) {
            Notification::assertSentTo(
                $recipient,
                NewOrderNotification::class,
                fn (NewOrderNotification $notification) => $notification->order->is($order),
            );
        }
    }

    public function test_operators_are_not_notified(): void
    {
        Notification::fake();

        $operator = User::factory()->operator()->create();

        $this->fireForNewOrder();

        Notification::assertNotSentTo($operator, NewOrderNotification::class);
    }

    public function test_every_eligible_recipient_receives_the_notification(): void
    {
        Notification::fake();

        User::factory()->count(5)->administrator()->create();
        User::factory()->count(3)->manager()->create();
        User::factory()->count(4)->operator()->create();

        $this->fireForNewOrder();

        Notification::assertCount(8);
    }

    public function test_repository_streams_recipients_in_chunks_instead_of_loading_all(): void
    {
        User::factory()->count(5)->administrator()->create();

        $chunks = [];

        app(UserRepository::class)->eachOrderNotifiable(
            function (Collection $recipients) use (&$chunks): void {
                $chunks[] = $recipients->count();
            },
            chunkSize: 2,
        );

        $this->assertSame([2, 2, 1], $chunks);
    }
}
