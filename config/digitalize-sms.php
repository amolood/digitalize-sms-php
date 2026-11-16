<?php

return [
    // Your API key from https://digitalize.sd/sms/api-keys — keep it in .env, never in code
    'api_key' => env('DIGITALIZE_SMS_KEY'),

    'base_url' => env('DIGITALIZE_SMS_BASE_URL', 'https://digitalize.sd/api/v1/sms'),

    // Seconds for a whole request, and to connect
    'timeout' => env('DIGITALIZE_SMS_TIMEOUT', 15),
    'connect_timeout' => 5,

    // Extra attempts after a timeout, 429 or 5xx. Sends are never duplicated (idempotency keys).
    'max_retries' => 2,
];
