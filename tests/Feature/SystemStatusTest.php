<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ReverbMetricsService;
use App\Services\SystemStatusService;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobPopping;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SystemStatusTest extends TestCase
{
    use RefreshDatabase;

    /** Ответ, которым health-эндпоинт SMS-шлюза отвечает в текущем тесте. */
    private ?PromiseInterface $otpGatewayResponse = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Не уходить в реальную сеть за health-эндпоинтом SMS-шлюза. Заглушка
        // читает свойство при каждом вызове, поэтому тест может подменить ответ.
        Http::fake(fn (): PromiseInterface => $this->otpGatewayResponse ?? Http::response(['clients' => 0]));

        // Pusher SDK ходит мимо фасада Http, поэтому Reverb подменяется целиком.
        $this->fakeReverbMetrics();
    }

    public function test_queue_status_is_ok_when_heartbeat_is_fresh(): void
    {
        Cache::put(SystemStatusService::HEARTBEAT_KEY, now()->timestamp, 180);

        $this->actingAs(User::factory()->create())
            ->getJson(route('system.status'))
            ->assertOk()
            ->assertJsonPath('queue.status', 'ok');
    }

    public function test_queue_status_is_error_when_heartbeat_is_missing(): void
    {
        Cache::forget(SystemStatusService::HEARTBEAT_KEY);

        $this->actingAs(User::factory()->create())
            ->getJson(route('system.status'))
            ->assertOk()
            ->assertJsonPath('queue.status', 'error');
    }

    public function test_queue_status_is_error_when_heartbeat_is_stale(): void
    {
        Cache::put(SystemStatusService::HEARTBEAT_KEY, now()->subSeconds(200)->timestamp, 180);

        $this->actingAs(User::factory()->create())
            ->getJson(route('system.status'))
            ->assertOk()
            ->assertJsonPath('queue.status', 'error');
    }

    public function test_worker_job_poll_writes_heartbeat(): void
    {
        Cache::forget(SystemStatusService::HEARTBEAT_KEY);

        event(new JobPopping('redis'));

        $this->assertNotNull(Cache::get(SystemStatusService::HEARTBEAT_KEY));
    }

    /**
     * `queue:listen` крутит `queue:work --once`, где демон-цикл (а значит и
     * событие `Looping`) не выполняется. Heartbeat обязан жить на `JobPopping`,
     * иначе локальный воркер вечно показывается отключённым.
     */
    public function test_heartbeat_does_not_depend_on_the_daemon_loop_event(): void
    {
        Cache::forget(SystemStatusService::HEARTBEAT_KEY);

        event(new Looping('redis', 'default'));

        $this->assertNull(Cache::get(SystemStatusService::HEARTBEAT_KEY));
    }

    public function test_processed_counter_grows_with_each_finished_job(): void
    {
        Cache::forget(SystemStatusService::processedKey());

        event(new JobProcessed('redis', $this->createMock(Job::class)));
        event(new JobProcessed('redis', $this->createMock(Job::class)));

        $this->assertSame(2, (int) Cache::get(SystemStatusService::processedKey()));

        $this->actingAs(User::factory()->create())
            ->getJson(route('system.status'))
            ->assertJsonPath('queue.processed', 2);
    }

    public function test_reverb_metrics_come_from_the_reverb_api(): void
    {
        $this->fakeReverbMetrics([
            'status' => 'ok',
            'channels' => 4,
            'connections' => 11,
            'latency_ms' => 7,
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('system.status'))
            ->assertOk()
            ->assertJsonPath('reverb.status', 'ok')
            ->assertJsonPath('reverb.channels', 4)
            ->assertJsonPath('reverb.connections', 11)
            ->assertJsonPath('reverb.latency_ms', 7);
    }

    public function test_reverb_reports_error_without_invented_metrics_when_unreachable(): void
    {
        $this->fakeReverbMetrics([
            'status' => 'error',
            'channels' => 0,
            'connections' => 0,
            'latency_ms' => null,
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('system.status'))
            ->assertOk()
            ->assertJsonPath('reverb.status', 'error')
            ->assertJsonPath('reverb.latency_ms', null);
    }

    public function test_otp_gateway_status_reflects_health_endpoint(): void
    {
        $this->otpGatewayResponse = Http::response(['clients' => 3]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('system.status'))
            ->assertOk()
            ->assertJsonPath('otp_gateway.status', 'ok')
            ->assertJsonPath('otp_gateway.clients', 3);
    }

    public function test_otp_gateway_is_error_when_health_endpoint_fails(): void
    {
        $this->otpGatewayResponse = Http::response(status: 500);

        $this->actingAs(User::factory()->create())
            ->getJson(route('system.status'))
            ->assertOk()
            ->assertJsonPath('otp_gateway.status', 'error')
            ->assertJsonPath('otp_gateway.clients', 0);
    }

    public function test_snapshot_is_cached_until_fresh_is_requested(): void
    {
        $user = User::factory()->create();

        // Reverb опрашивается только на первом запросе и на запросе с fresh=1:
        // средний запрос обязан отдаться из кэша.
        $this->mock(ReverbMetricsService::class)
            ->shouldReceive('metrics')
            ->twice()
            ->andReturn(['status' => 'ok', 'channels' => 2, 'connections' => 2, 'latency_ms' => 5]);

        $this->actingAs($user)->getJson(route('system.status'))->assertOk();
        $this->actingAs($user)->getJson(route('system.status'))->assertOk();
        $this->actingAs($user)->getJson(route('system.status', ['fresh' => 1]))->assertOk();
    }

    public function test_endpoint_requires_authentication(): void
    {
        $this->getJson(route('system.status'))->assertUnauthorized();
    }

    /**
     * @param  array{status: string, channels: int, connections: int, latency_ms: int|null}|null  $metrics
     */
    private function fakeReverbMetrics(?array $metrics = null): void
    {
        $metrics ??= ['status' => 'ok', 'channels' => 0, 'connections' => 0, 'latency_ms' => 1];

        $this->mock(ReverbMetricsService::class)
            ->shouldReceive('metrics')
            ->andReturn($metrics);
    }
}
