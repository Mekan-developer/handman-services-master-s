<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Master;
use App\Models\Order;
use App\Observers\ClientObserver;
use App\Observers\MasterObserver;
use App\Observers\OrderObserver;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\ModemSmsSender;
use App\Services\Sms\SmsSender;
use App\Services\SystemStatusService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\Events\JobPopping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsSender::class, function (Application $app): SmsSender {
            return match (config('sms.driver')) {
                'modem' => new ModemSmsSender(config('sms.gateway_url'), config('sms.otp_secret')),
                'log' => $app->isProduction()
                    ? throw new RuntimeException('The "log" SMS driver is not allowed in production — set SMS_DRIVER=modem.')
                    : new LogSmsSender,
                default => throw new InvalidArgumentException('Unknown SMS driver "'.config('sms.driver').'".'),
            };
        });
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
     * Пишет heartbeat живого queue-воркера перед каждой попыткой снять задачу.
     *
     * Слушаем именно `JobPopping`, а не `Queue::looping`: событие `Looping`
     * диспатчится только из демон-цикла `queue:work`, тогда как локальный
     * контейнер поднят через `queue:listen` (каждая итерация — отдельный
     * `queue:work --once`, минующий этот цикл). `JobPopping` срабатывает в
     * `Worker::getNextJob()` и потому одинаково работает в обоих режимах.
     */
    private function registerQueueHeartbeat(): void
    {
        Event::listen(JobPopping::class, function (): void {
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
