<?php

namespace App\Actions;

use App\Enums\OtpDeliveryChannel;
use App\Enums\OtpRecipientType;
use App\Events\PendingOtpCreated;
use App\Events\SmsCodeRequested;
use App\Exceptions\OtpException;
use App\Repositories\PendingOtpRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Generates an OTP and tries to deliver it over the SMS gateway. When the
 * gateway is down the code is still issued, but it is parked in the admin
 * panel so an operator can dictate it to the caller instead of blocking login.
 */
class DispatchOtpAction
{
    public function __construct(private readonly PendingOtpRepository $pendingOtps) {}

    public function handle(string $phone, OtpRecipientType $recipient, ?string $recipientName = null): OtpDeliveryChannel
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes((int) config('services.otp.ttl_minutes'));

        $channel = OtpDeliveryChannel::Sms;

        try {
            SmsCodeRequested::dispatch($phone, $code);
        } catch (OtpException|RuntimeException $e) {
            if (! $e instanceof OtpException) {
                // Misconfigured driver (no gateway URL or secret): login must
                // still work, but the cause has to be visible to whoever deploys.
                Log::error("SMS driver misconfigured: {$e->getMessage()}");
            }

            $channel = OtpDeliveryChannel::Manual;

            $pendingOtp = $this->pendingOtps->replaceForPhone([
                'phone' => $phone,
                'code' => $code,
                'recipient_type' => $recipient,
                'recipient_name' => $recipientName,
                'expires_at' => $expiresAt,
            ]);

            // Queued broadcast — open admin panels get the code without waiting
            // on Reverb inside the caller's login request.
            PendingOtpCreated::dispatch($pendingOtp);

            Log::warning("OTP for {$phone} parked for manual delivery: SMS gateway unavailable.");
        }

        Cache::put($recipient->cacheKey($phone), $code, $expiresAt);

        return $channel;
    }
}
