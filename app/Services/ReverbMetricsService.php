<?php

namespace App\Services;

use Pusher\Pusher;
use Pusher\PusherException;
use Throwable;

/**
 * Снимает живые метрики Reverb через его Pusher-совместимый HTTP API.
 *
 * Reverb поднимает подписанные эндпоинты `/apps/{id}/channels` и
 * `/apps/{id}/connections` на том же порту, что и WebSocket-сервер, поэтому
 * отдельная телеметрия не нужна — данные берутся прямо у сервера.
 */
class ReverbMetricsService
{
    private const REQUEST_TIMEOUT = 2;

    /**
     * @return array{status: string, channels: int, connections: int, latency_ms: int|null}
     */
    public function metrics(): array
    {
        try {
            $pusher = $this->pusher();

            $startedAt = hrtime(true);
            $channels = $pusher->get('/channels');
            $latencyMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

            $connections = $pusher->get('/connections');
        } catch (Throwable) {
            return $this->unavailable();
        }

        return [
            'status' => 'ok',
            'channels' => count((array) ($channels->channels ?? [])),
            'connections' => (int) ($connections->connections ?? 0),
            'latency_ms' => $latencyMs,
        ];
    }

    /**
     * @throws PusherException
     */
    private function pusher(): Pusher
    {
        /** @var array{key: ?string, secret: ?string, app_id: ?string, options: array<string, mixed>} $config */
        $config = config('broadcasting.connections.reverb');

        return new Pusher(
            (string) $config['key'],
            (string) $config['secret'],
            (string) $config['app_id'],
            [
                'host' => $config['options']['host'] ?? '127.0.0.1',
                'port' => $config['options']['port'] ?? 8080,
                'scheme' => $config['options']['scheme'] ?? 'http',
                'useTLS' => (bool) ($config['options']['useTLS'] ?? false),
                'timeout' => self::REQUEST_TIMEOUT,
            ]
        );
    }

    /**
     * @return array{status: string, channels: int, connections: int, latency_ms: null}
     */
    private function unavailable(): array
    {
        return [
            'status' => 'error',
            'channels' => 0,
            'connections' => 0,
            'latency_ms' => null,
        ];
    }
}
