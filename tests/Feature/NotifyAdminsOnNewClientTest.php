<?php

namespace Tests\Feature;

use App\Events\ClientCreated;
use App\Listeners\NotifyAdminsOnNewClient;
use App\Models\Client;
use App\Models\User;
use App\Notifications\NewClientNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotifyAdminsOnNewClientTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function fireForNewClient(): Client
    {
        $client = Client::factory()->create();

        app(NotifyAdminsOnNewClient::class)->handle(new ClientCreated($client));

        return $client;
    }

    public function test_administrators_and_managers_are_notified(): void
    {
        Notification::fake();

        $administrator = User::factory()->administrator()->create();
        $manager = User::factory()->manager()->create();

        $client = $this->fireForNewClient();

        foreach ([$administrator, $manager] as $recipient) {
            Notification::assertSentTo(
                $recipient,
                NewClientNotification::class,
                fn (NewClientNotification $notification) => $notification->client->is($client),
            );
        }
    }

    public function test_operators_are_not_notified(): void
    {
        Notification::fake();

        $operator = User::factory()->operator()->create();

        $this->fireForNewClient();

        Notification::assertNotSentTo($operator, NewClientNotification::class);
    }

    public function test_every_eligible_recipient_receives_the_notification(): void
    {
        Notification::fake();

        User::factory()->count(5)->administrator()->create();
        User::factory()->count(3)->manager()->create();
        User::factory()->count(4)->operator()->create();

        $this->fireForNewClient();

        Notification::assertCount(8);
    }
}
