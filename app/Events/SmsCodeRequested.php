<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An OTP code has to reach the phone by SMS.
 *
 * Handled synchronously by SendSmsCode: the caller needs to know right away
 * whether the code left, so it can fall back to manual delivery.
 */
class SmsCodeRequested
{
    use Dispatchable;

    public function __construct(
        public readonly string $phone,
        public readonly string $code,
    ) {}
}
