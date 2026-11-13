# Baxela Sandbox Gateway

A Baxela payment-driver extension with **zero external API calls** — the
reference implementation for extension authors, and a permanent
dev/testing gateway: checkout flows work without real credentials.

## How it works

- Registers the driver under the `sandbox` method slug at boot
  (`config(['payment.drivers.sandbox' => SandboxPaymentDriver::class])`)
  — no core file changes.
- `initiate()` redirects to the storefront return page; the payment id
  doubles as the stored `transaction_id`.
- Settlement fires through the standard parameterized webhook route:
  `POST /api/v1/payment/webhook/sandbox` with body
  `{"transaction_id": "<payment id>", "status": "success"}` and header
  `X-Sandbox-Signature: <hex hmac>` where the signature is
  `hash_hmac('sha256', $rawBody, $secret)`. Signature verification is
  real and enforced — never skip it in anything copied from here.
- Refunds are definitive no-ops (no money ever moved) — the manual
  driver's semantics; the refund ledger settles immediately.
- `install()` seeds an **inactive** `payment_methods` row for `sandbox`;
  an admin activates it. `uninstall()` removes it.

## Install (inside a Baxela backend)

```bash
php artisan baxela:install baxela/baxela-sandbox-gateway
php artisan baxela:list                 # row in core_extensions, version live
php artisan tinker                      # activate:
# PaymentMethod::where('method', 'sandbox')->update(['is_active' => true]);
```

During development this package is dev-mounted via the backend's
composer path repository (`extensions/*`), so it resolves locally with
no marketplace.

## Configuration

Runtime defaults are env-backed and merged at boot (a disabled
extension leaves nothing behind):

| Env | Default | Purpose |
|---|---|---|
| `SANDBOX_WEBHOOK_SECRET` | `sandbox-secret` | HMAC secret for webhook signature verification |
| `SANDBOX_RETURN_URL` | `http://localhost:3000/en/payment/return?order_code={order_code}` | Storefront return page |

`config/sandbox.php` is a reference copy of these keys — publishing it
is never required.

## Tests

This package has no vendor tree of its own; its Pest suite runs from
the host backend (where the driver contracts and Pest live):

```bash
cd apps/backend
vendor/bin/pest extensions/baxela-sandbox-gateway/tests
```

This wiring is deliberate — the alternative (a standalone vendor
install per extension repo) doubles dependency resolution for a suite
that exists to test integration with the host. Revisit if the extension
grows dependencies of its own.

## Dev-mount caveat

Path-repository packages are symlinked; combined with
`optimize-autoloader`, class discovery can lag after changes — run
`composer dump-autoload` in the backend once if the provider class is
not found. Dist installs (the marketplace, later) are unaffected. The
`version` field in composer.json is deliberate: composer's stability
gate does not guess versions from git tags on path repositories, so the
dev mount declares it explicitly and keeps it in sync with the git tag
(the marketplace's dist flow is tag-driven and ignores the field).

## Identity notes for authors

The manifest's `slug` is the **composer package name** (`baxela/baxela-sandbox-gateway`)
— the identity `core_extensions` rows and every `baxela:*` command key
on. The checkout-facing driver key (`sandbox`) is a separate concern:
it is the config registry key and the webhook URL segment, chosen by
the extension itself in `extensionBoot()`.

## 0.0.3

- Trivial change for the M.5 update proof.

