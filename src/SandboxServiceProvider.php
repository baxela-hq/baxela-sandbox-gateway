<?php

namespace Baxela\Sandbox;

use Modules\Core\Extensions\Extension;
use Modules\Payment\Models\PaymentMethod;
use Modules\Payment\Schemas\Payment\PaymentMethodSchema;

/**
 * The reference Baxela extension provider. extensionBoot() is the
 * extension's entire integration surface — register the driver under
 * its method slug and merge its config defaults into the payment
 * namespace — and it only runs when every boot guard passes. No core
 * file changes per extension, ever.
 */
class SandboxServiceProvider extends Extension
{
    /**
     * Runtime manifest. The slug is the composer package name — the
     * identity core_extensions rows and baxela:* commands key on; the
     * checkout-facing driver slug ('sandbox') is a separate, informational
     * field consumed by humans and later the marketplace.
     *
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        return [
            'slug' => 'baxela/baxela-sandbox-gateway',
            'name' => 'Sandbox Gateway',
            'type' => 'payment-driver',
            'version' => '0.0.1',
            'compat' => ['core' => '^0.1'],
        ];
    }

    protected function extensionBoot(): void
    {
        config(['payment.drivers.sandbox' => SandboxPaymentDriver::class]);

        // Env-backed defaults, merged only when the extension boots: a
        // disabled extension leaves no trace in the payment namespace.
        config(['payment.sandbox' => [
            'webhook_secret' => config('payment.sandbox.webhook_secret')
                ?? env('SANDBOX_WEBHOOK_SECRET', 'sandbox-secret'),
            'return_url' => config('payment.sandbox.return_url')
                ?? env('SANDBOX_RETURN_URL', 'http://localhost:3000/en/payment/return?order_code={order_code}'),
        ]]);
    }

    /**
     * baxela:install hook: seed the checkout catalog row, inactive by
     * default like every other driver — an admin activates it.
     */
    public function install(): void
    {
        PaymentMethod::query()->firstOrCreate(
            [PaymentMethodSchema::METHOD => 'sandbox'],
            [
                PaymentMethodSchema::IS_ACTIVE => false,
                PaymentMethodSchema::SORT_ORDER => 0,
            ],
        );
    }

    /**
     * baxela:remove hook: drop the catalog row so a reinstall starts
     * from the same inactive-by-default state.
     */
    public function uninstall(): void
    {
        PaymentMethod::query()
            ->where(PaymentMethodSchema::METHOD, 'sandbox')
            ->delete();
    }
}
