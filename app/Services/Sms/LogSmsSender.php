<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Development driver: writes the code to the application log instead of
 * sending an SMS. Never bound in production — see AppServiceProvider.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $code): void
    {
        Log::info("[sms:log] OTP for {$phone}: {$code}");
    }

    public function status(): array
    {
        return ['reachable' => true, 'connected' => true, 'clients' => 0];
    }
}
