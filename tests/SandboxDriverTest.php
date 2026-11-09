<?php

use Baxela\Sandbox\SandboxPaymentDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentInitiateInput;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Gateways\PaymentDriverManager;

/**
 * The sandbox extension's own suite — run from the host backend
 * (see README): the contracts under test live there.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config(['payment.sandbox.webhook_secret' => 'test-secret']);
});

function sandboxInitiateInput(int $paymentId = 9, string $orderCode = 'ABCDEFGH'): PaymentInitiateInput
{
    return new PaymentInitiateInput(
        payment_id: $paymentId,
        order_id: 1,
        amount: 305.0,
        method: 'sandbox',
        order_code: $orderCode,
        currency: 'USD',
        currency_decimal_places: 2,
    );
}

it('initiates a redirect whose transaction id is the payment id', function () {
    config(['payment.sandbox.return_url' => 'https://shop.test/en/payment/return?order_code={order_code}']);

    $result = (new SandboxPaymentDriver)->initiate(sandboxInitiateInput());

    expect($result->redirect_url)->toBe('https://shop.test/en/payment/return?order_code=ABCDEFGH')
        ->and($result->transaction_id)->toBe('9');
});

it('settles a webhook whose HMAC signature verifies', function () {
    $body = json_encode(['transaction_id' => '9', 'status' => 'success']);

    $request = Request::create(
        '/api/v1/payment/webhook/sandbox',
        'POST',
        [],
        [],
        [],
        ['HTTP_X_Sandbox_Signature' => hash_hmac('sha256', $body, 'test-secret'), 'CONTENT_TYPE' => 'application/json'],
        $body,
    );

    $result = (new SandboxPaymentDriver)->handleWebhook($request);

    expect($result->transaction_id)->toBe('9')
        ->and($result->status)->toBe('success')
        ->and($result->gateway_transaction_id)->toBe('sandbox-9');
});

it('rejects a webhook with a wrong signature, never an unsigned one', function () {
    $body = json_encode(['transaction_id' => '9', 'status' => 'success']);

    foreach (['wrong-signature', ''] as $signature) {
        $request = Request::create(
            '/api/v1/payment/webhook/sandbox',
            'POST',
            [],
            [],
            [],
            ['HTTP_X_Sandbox_Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $body,
        );

        expect(fn () => (new SandboxPaymentDriver)->handleWebhook($request))
            ->toThrow(PaymentException::webhookInvalid());
    }
});

it('settles refunds definitively — no gateway, no pending state', function () {
    $result = (new SandboxPaymentDriver)->refund(new Modules\Core\Contracts\Gateways\Payment\DTOs\PaymentRefundInput(
        payment_id: 9,
        order_id: 1,
        transaction_id: '9',
        amount: 10.0,
        currency: 'USD',
        currency_decimal_places: 2,
        idempotency_key: 'irrelevant',
        gateway_transaction_id: null,
    ));

    expect($result->accepted_pending)->toBeFalse();
});

it('declares exactly the credential it needs to the platform', function () {
    $manager = app(PaymentDriverManager::class);
    config(['payment.drivers.sandbox' => SandboxPaymentDriver::class]);

    expect($manager->isConfigured('sandbox'))->toBeTrue();

    config(['payment.sandbox.webhook_secret' => null]);

    expect($manager->isConfigured('sandbox'))->toBeFalse();
});
