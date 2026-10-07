<?php

namespace Tests\Feature;

use App\Enums\OtpRecipientType;
use App\Events\SmsCodeRequested;
use App\Exceptions\OtpException;
use App\Listeners\SendSmsCode;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\ModemSmsSender;
use App\Services\Sms\SmsSender;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class SmsSenderTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function modem(?string $url = 'http://sms-gateway.test', ?string $secret = 'test-otp-secret'): ModemSmsSender
    {
        return new ModemSmsSender($url, $secret);
    }

    public function test_modem_posts_the_code_with_the_secret_header(): void
    {
        Http::fake(['*/emit-otp' => Http::response(['message' => 'OTP event emitted'])]);

        $this->modem()->send('+99361234567', '123456');

        Http::assertSent(fn ($request) => $request->url() === 'http://sms-gateway.test/emit-otp'
            && $request->method() === 'POST'
            && $request->hasHeader('X-Otp-Secret', 'test-otp-secret')
            && $request['phone_number'] === '61234567'
            && $request['otp'] === '123456');
        $this->assertNotNull(cache(ModemSmsSender::LAST_SENT_KEY));
    }

    public function test_modem_reports_a_rejected_secret(): void
    {
        Http::fake(['*/emit-otp' => Http::response(['message' => 'Unauthorized'], 401)]);

        $this->expectException(OtpException::class);
        $this->expectExceptionMessage(__('api.otp.send_failed'));

        $this->modem()->send('+99361234567', '123456');
    }

    public function test_modem_reports_that_no_phone_is_connected(): void
    {
        Http::fake(['*/emit-otp' => Http::response(['message' => 'No gateway client connected'], 503)]);

        $this->expectException(OtpException::class);
        $this->expectExceptionMessage(__('api.otp.gateway_not_connected'));

        $this->modem()->send('+99361234567', '123456');
    }

    public function test_modem_reports_an_unreachable_gateway(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this->expectException(OtpException::class);
        $this->expectExceptionMessage(__('api.otp.send_failed'));

        $this->modem()->send('+99361234567', '123456');
    }

    public function test_modem_refuses_to_send_without_a_gateway_url(): void
    {
        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SMS_GATEWAY_URL');

        $this->modem(url: '')->send('+99361234567', '123456');
    }

    public function test_modem_refuses_to_send_without_a_secret(): void
    {
        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('OTP_SECRET');

        try {
            $this->modem(secret: null)->send('+99361234567', '123456');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_modem_status_reads_the_health_endpoint(): void
    {
        Http::fake(['*/health' => Http::response(['status' => 'ok', 'clients' => 2])]);

        $this->assertSame(
            ['reachable' => true, 'connected' => true, 'clients' => 2],
            $this->modem()->status(),
        );

        Http::assertSent(fn ($request) => $request->url() === 'http://sms-gateway.test/health'
            && ! $request->hasHeader('X-Otp-Secret'));
    }

    public function test_modem_status_without_phones_is_reachable_but_not_connected(): void
    {
        Http::fake(['*/health' => Http::response(['status' => 'ok', 'clients' => 0])]);

        $this->assertSame(
            ['reachable' => true, 'connected' => false, 'clients' => 0],
            $this->modem()->status(),
        );
    }

    public function test_modem_status_logs_and_survives_an_unreachable_gateway(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));
        Log::spy();

        $this->assertSame(
            ['reachable' => false, 'connected' => false, 'clients' => 0],
            $this->modem()->status(),
        );

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_modem_driver_is_resolved_from_config(): void
    {
        config(['sms.driver' => 'modem']);

        $this->assertInstanceOf(ModemSmsSender::class, app(SmsSender::class));
    }

    public function test_log_driver_writes_the_code_to_the_log(): void
    {
        config(['sms.driver' => 'log']);
        Log::spy();

        app(SmsSender::class)->send('+99361234567', '123456');

        Log::shouldHaveReceived('info')->once()->withArgs(fn (string $message) => str_contains($message, '123456'));
    }

    public function test_log_driver_is_refused_in_production(): void
    {
        config(['sms.driver' => 'log']);
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);

        app(SmsSender::class);
    }

    public function test_unknown_driver_is_refused(): void
    {
        config(['sms.driver' => 'pigeon']);

        $this->expectException(InvalidArgumentException::class);

        app(SmsSender::class);
    }

    public function test_requesting_a_code_goes_through_the_sms_event(): void
    {
        Event::fake([SmsCodeRequested::class]);

        $this->postJson(route('api.v1.client.auth.request-otp'), ['phone' => '+99361234567'])->assertOk();

        Event::assertDispatched(SmsCodeRequested::class, fn (SmsCodeRequested $event) => $event->phone === '+99361234567'
            && $event->code === cache(OtpRecipientType::Client->cacheKey('+99361234567')));
        Event::assertListening(SmsCodeRequested::class, SendSmsCode::class);
    }

    public function test_a_misconfigured_driver_falls_back_to_manual_delivery(): void
    {
        config(['sms.otp_secret' => null]);
        Http::fake();

        $this->postJson(route('api.v1.client.auth.request-otp'), ['phone' => '+99361234567'])
            ->assertOk()
            ->assertJsonPath('delivery', 'manual');

        Http::assertNothingSent();
    }

    public function test_log_driver_counts_as_delivered(): void
    {
        config(['sms.driver' => 'log']);
        Http::fake();

        $this->postJson(route('api.v1.client.auth.request-otp'), ['phone' => '+99361234567'])
            ->assertOk()
            ->assertJsonPath('delivery', 'sms');

        Http::assertNothingSent();
        $this->assertInstanceOf(LogSmsSender::class, app(SmsSender::class));
    }
}
