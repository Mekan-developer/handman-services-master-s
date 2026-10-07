<?php

namespace App\Services\Sms;

use App\Exceptions\OtpException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Hands OTP codes to the socket-server gateway, which re-emits them to the
 * connected SMS phone. The phone sends the actual SMS.
 */
class ModemSmsSender implements SmsSender
{
    public const LAST_SENT_KEY = 'otp_gateway:last_sent';

    public function __construct(
        private readonly ?string $gatewayUrl,
        private readonly ?string $otpSecret,
    ) {}

    public function send(string $phone, string $code): void
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders(['X-Otp-Secret' => $this->secret()])
                ->post($this->url().'/emit-otp', [
                    'phone_number' => $this->toLocalFormat($phone),
                    'otp' => $code,
                ]);
        } catch (ConnectionException $e) {
            Log::error("SMS gateway unreachable for {$phone}: {$e->getMessage()}");

            throw OtpException::sendFailed();
        }

        if ($response->status() === 503) {
            Log::warning("SMS gateway has no phone connected, OTP for {$phone} not sent");

            throw OtpException::gatewayNotConnected();
        }

        if (! $response->successful()) {
            Log::error("SMS gateway rejected OTP for {$phone}: {$response->status()} {$response->body()}");

            throw OtpException::sendFailed();
        }

        Cache::put(self::LAST_SENT_KEY, now()->format('H:i'), now()->addDay());
    }

    public function status(): array
    {
        try {
            $response = Http::timeout(2)->get($this->url().'/health');

            if ($response->successful()) {
                $clients = (int) ($response->json('clients') ?? 0);

                return ['reachable' => true, 'connected' => $clients > 0, 'clients' => $clients];
            }

            Log::warning("SMS gateway health check failed: {$response->status()}");
        } catch (Throwable $e) {
            Log::warning("SMS gateway health check failed: {$e->getMessage()}");
        }

        return ['reachable' => false, 'connected' => false, 'clients' => 0];
    }

    private function url(): string
    {
        if (blank($this->gatewayUrl)) {
            throw new RuntimeException('SMS_GATEWAY_URL is not set for the modem SMS driver.');
        }

        return rtrim($this->gatewayUrl, '/');
    }

    private function secret(): string
    {
        if (blank($this->otpSecret)) {
            throw new RuntimeException('OTP_SECRET is not set for the modem SMS driver.');
        }

        return $this->otpSecret;
    }

    /** The phone receives 8 digits without the +993 country code. */
    private function toLocalFormat(string $phone): string
    {
        return str_starts_with($phone, '+993') ? substr($phone, 4) : $phone;
    }
}
