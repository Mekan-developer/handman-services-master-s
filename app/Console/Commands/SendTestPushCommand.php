<?php

namespace App\Console\Commands;

use App\Notifications\TestPushNotification;
use App\Repositories\ClientRepository;
use Illuminate\Console\Command;

class SendTestPushCommand extends Command
{
    protected $signature = 'fcm:test
        {client : Client id}
        {--title=Takykakym : Notification title}
        {--body=Test push : Notification body}';

    protected $description = 'Send a test FCM push to every registered phone of a client';

    public function handle(ClientRepository $clientRepository): int
    {
        $client = $clientRepository->findOrFail((int) $this->argument('client'));

        $tokenCount = count(array_merge(...array_values($client->routeNotificationForFcm())));

        if ($tokenCount === 0) {
            $this->error("Client #{$client->id} has no registered devices.");

            return self::FAILURE;
        }

        $client->notify(new TestPushNotification(
            (string) $this->option('title'),
            (string) $this->option('body'),
        ));

        $remaining = count(array_merge(...array_values($client->routeNotificationForFcm())));

        $this->info("Sent to {$tokenCount} device(s); {$remaining} token(s) still valid.");

        return self::SUCCESS;
    }
}
