<?php

return [

    /*
    | OTP lifetime and the local-only fixed code.
    | Draft lifetime is an implementation default; the product fixes the OTP window at 120 seconds.
    */

    'otp' => [
        'ttl_seconds' => 120,
        'max_attempts' => 5,
        'draft_ttl_seconds' => 1800,
        'fixed_code' => env('APP_ENV') === 'local' ? '12345' : null,
    ],

    /*
    | Open-checkout lifetime. The product requires an expiry, without a duration.
    */

    'checkout' => [
        'ttl_seconds' => 1800,
    ],

    /*
    | The product suggests seven days for an open invitation.
    */

    'invitation' => [
        'ttl_seconds' => 604800,
    ],

];
