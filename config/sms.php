<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS driver
    |--------------------------------------------------------------------------
    |
    | "modem" — OTP codes go through the socket-server gateway to the SMS phone.
    | "log"   — codes are only written to the application log (development).
    |           Refused in production.
    |
    */

    'driver' => env('SMS_DRIVER', 'modem'),

    /*
    |--------------------------------------------------------------------------
    | socket-server gateway
    |--------------------------------------------------------------------------
    |
    | gateway_url — where Laravel reaches socket-server: http://127.0.0.1:3000
    |               on one machine, http://sms-gateway:3000 inside Docker.
    | otp_secret  — shared with socket-server and the phone's Auth Token.
    |
    */

    'gateway_url' => env('SMS_GATEWAY_URL', 'http://127.0.0.1:3000'),

    'otp_secret' => env('OTP_SECRET'),

    // Human-readable name of the sending phone, shown on the system status card.
    'device_label' => env('SMS_DEVICE_LABEL'),

];
