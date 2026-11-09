<?php

/**
 * Reference copy of the extension's configuration keys. At runtime the
 * provider merges these defaults (env-backed) into the payment
 * namespace inside extensionBoot() — a disabled extension leaves no
 * trace — so publishing this file is never required.
 */
return [
    // Verifies the X-Sandbox-Signature header on
    // POST /api/v1/payment/webhook/sandbox. The default keeps the
    // driver configured out of the box for local development; set a
    // real secret anywhere the extension runs.
    'webhook_secret' => env('SANDBOX_WEBHOOK_SECRET', 'sandbox-secret'),

    // Storefront return page; {order_code} is replaced with the
    // order's public code.
    'return_url' => env('SANDBOX_RETURN_URL', 'http://localhost:3000/en/payment/return?order_code={order_code}'),
];
