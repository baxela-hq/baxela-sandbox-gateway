<?php

namespace Baxela\Sandbox;

use Illuminate\Http\Request;
use Modules\Core\Contracts\Gateways\Payment\ConfigurableDriver;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentGatewayResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundInput;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundResult;
use Modules\Core\Contracts\Gateways\Payment\DTOs\WebhookResult;
use Modules\Core\Contracts\Gateways\Payment\RefundableDriver;
use Modules\Payment\Exceptions\PaymentException;

/**
 * A real payment driver with no external API. The signature check is
 * genuine HMAC-SHA256 over the raw body — this file is the reference
 * for marketplace authors, so verification must be enforced exactly as
 * a hosted gateway would, never skipped.
 */
class SandboxPaymentDriver implements ConfigurableDriver, RefundableDriver
{
    public function credentialKeys(): array
    {
        return ['webhook_secret'];
    }

    public function initiate(PaymentInitiateInput $input): PaymentGatewayResult
    {
        // No hosted checkout exists: the redirect lands on the
        // storefront return page, and settlement happens when a signed
        // webhook fires (Bruno, a test, or the return page's harness).
        // The payment id doubles as the transaction_id the webhook
        // matches on — the Adyen pattern.
        $url = str_replace(
            '{order_code}',
            (string) $input->order_code,
            (string) config('payment.sandbox.return_url'),
        );

        return new PaymentGatewayResult(
            redirect_url: $url,
            transaction_id: (string) $input->payment_id,
        );
    }

    public function handleWebhook(Request $request): WebhookResult
    {
        $secret = (string) config('payment.sandbox.webhook_secret');

        if ($secret === '') {
            throw PaymentException::gatewayUnconfigured();
        }

        $body = (string) $request->getContent();

        $expected = hash_hmac('sha256', $body, $secret);

        if (! hash_equals($expected, (string) $request->header('X-Sandbox-Signature', ''))) {
            throw PaymentException::webhookInvalid();
        }

        $payload = json_decode($body, true);

        if (! is_array($payload)) {
            throw PaymentException::webhookInvalid();
        }

        return new WebhookResult(
            transaction_id: (string) ($payload['transaction_id'] ?? ''),
            status: (string) ($payload['status'] ?? ''),
            gateway_transaction_id: 'sandbox-'.((string) ($payload['transaction_id'] ?? '')),
        );
    }

    public function refund(PaymentRefundInput $input): PaymentRefundResult
    {
        // No money ever moved, so there is nothing to move back: the
        // refund is definitive the moment it is recorded — the manual
        // driver's semantics.
        return new PaymentRefundResult;
    }
}
