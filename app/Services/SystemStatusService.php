<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Собирает состояние инфраструктуры для мониторинга в админке.
 *
 * Все значения снимаются с живых источников: heartbeat воркера, Pusher-API
 * Reverb и health-эндпоинт SMS-шлюза. Результат кэшируется на короткий срок,
 * чтобы открытые вкладки админов не устраивали шторм внешних запросов.
 */
class SystemStatusService
{
    public const HEARTBEAT_KEY = 'queue:worker_heartbeat';

    public const PROCESSED_KEY_PREFIX = 'queue:processed:';

    private const CACHE_KEY = 'system:status';

    private const CACHE_TTL = 10;

    private const HEARTBEAT_MAX_AGE = 120;

    public function __construct(private readonly ReverbMetricsService $reverbMetrics) {}

    /**
     * @return array{
     *     queue: array{status: string, pending: int, processed: int},
     *     reverb: array{status: string, channels: int, connections: int, latency_ms: int|null},
     *     otp_gateway: array{status: string, clients: int, last_sent: string|null},
     *     checked_at: string
     * }
     */
    public function snapshot(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn (): array => [
            'queue' => $this->queue(),
            'reverb' => $this->reverbMetrics->metrics(),
            'otp_gateway' => $this->otpGateway(),
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Ключ дневного счётчика обработанных задач.
     */
    public static function processedKey(?Carbon $date = null): string
    {
        return self::PROCESSED_KEY_PREFIX.($date ?? now())->toDateString();
    }

    /**
     * @return array{status: string, pending: int, processed: int}
     */
    private function queue(): array
    {
        $heartbeat = Cache::get(self::HEARTBEAT_KEY);
        $isAlive = $heartbeat && now()->timestamp - $heartbeat <= self::HEARTBEAT_MAX_AGE;

        return [
            'status' => $isAlive ? 'ok' : 'error',
            'pending' => $this->pendingJobs(),
            'processed' => (int) Cache::get(self::processedKey(), 0),
        ];
    }

    /**
     * Длина очереди по умолчанию у текущего драйвера (Redis LLEN / SELECT COUNT).
     */
    private function pendingJobs(): int
    {
        try {
            return Queue::size();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return array{status: string, clients: int, last_sent: string|null}
     */
    private function otpGateway(): array
    {
        $lastSent = Cache::get('otp_gateway:last_sent');

        try {
            $response = Http::timeout(2)->get(
                rtrim((string) config('services.sms_gateway.url'), '/').'/health'
            );

            if ($response->successful()) {
                return [
                    'status' => 'ok',
                    'clients' => (int) ($response->json('clients') ?? 0),
                    'last_sent' => $lastSent,
                ];
            }
        } catch (Throwable) {
        }

        return ['status' => 'error', 'clients' => 0, 'last_sent' => $lastSent];
    }
}
