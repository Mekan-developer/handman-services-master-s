<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Master;
use App\Models\Order;
use App\Observers\ClientObserver;
use App\Observers\MasterObserver;
use App\Observers\OrderObserver;
use App\Services\SystemStatusService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Client::observe(ClientObserver::class);
        Master::observe(MasterObserver::class);
        Order::observe(OrderObserver::class);

        $this->registerQueueHeartbeat();
        $this->registerProcessedJobsCounter();
    }

    /**
     * Пишет heartbeat живого queue-воркера на каждой итерации его цикла.
     *
     * Событие `Queue::looping` срабатывает только пока запущен `queue:work`,
     * поэтому индикатор очереди в админке отражает именно состояние воркера и
     * больше не зависит от планировщика (`schedule:work`).
     */
    private function registerQueueHeartbeat(): void
    {
        Queue::looping(function (): void {
            Cache::put(SystemStatusService::HEARTBEAT_KEY, now()->timestamp, 180);
        });
    }

    /**
     * Считает успешно обработанные задачи за текущие сутки.
     *
     * Счётчик живёт в кэше до конца дня и питает плитку «Обработано» в
     * мониторинге админки — драйверы очередей своей статистики не хранят.
     */
    private function registerProcessedJobsCounter(): void
    {
        Queue::after(function (): void {
            $key = SystemStatusService::processedKey();

            if (! Cache::add($key, 1, now()->endOfDay())) {
                Cache::increment($key);
            }
        });
    }
}
