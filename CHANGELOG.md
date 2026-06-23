# Changelog

All notable changes to `laravel-pelecard` will be documented in this file.

## v2.1.0 - 2026-06-23

### Added
- **Configurable billable model.** The model that owns subscriptions, transactions and the saved card token is now set via `config('pelecard.model')` (default `App\Models\User`), so the package can bill a `Tenant`, `Team`, or any other model — not just `users`.
  - `Subscription` and `PelecardTransaction` gained an `owner()` relationship (with `user()` kept as a deprecated alias) that resolves the configured model and its foreign key, mirroring Laravel Cashier's `owner()` / `getForeignKey()` pattern.
  - The package migrations now derive the billable **table and foreign key from the configured model** (e.g. `tenants` / `tenant_id`), so no migration editing is required to bill a non-User model. With the default `App\Models\User` the schema is unchanged (`users` / `user_id`), so this is backward-compatible.

## v2.0.0 - 2026-06-23

This release brings the package up to date with current Laravel and Laravel
Cashier conventions. It contains **breaking changes** to the subscription schema
and API — see the upgrade notes below.

### Added
- **Laravel 13 support** — added `^13.0` to the `illuminate/support`, `illuminate/database`, and `illuminate/contracts` constraints (now supports Laravel 10.x–13.x). Also added PHP 8.4 / 8.5 support.
- Cashier-style query helpers on `Billable`: `subscribedToPrice()`, `subscribedToProduct()`, `hasIncompletePayment()`, and `onTrial($type, $price)`.
- Cashier-style state helpers on `Subscription`: `canceled()`, `ended()`, `pastDue()`, `incomplete()`, `hasIncompletePayment()`, `hasPrice()`, `hasProduct()`, `hasSinglePrice()`, `hasMultiplePrices()`, `findItemOrFail()`, `cancelAt()`, `swapAndInvoice()`.
- Multi-price subscriptions: `newSubscription($type, $prices)` and `swap()` now accept an array of prices; additional prices are stored in `subscription_items`.
- `SubscriptionBuilder` gained `price()`, `quantity($qty, $price)`, and `anchorBillingCycleOn()`.
- A `pelecard_status` column on `subscriptions` and a `pelecard_product` column on `subscription_items`.
- Test coverage for the subscription lifecycle and payment-method handling.

### Changed (breaking)
- **Subscription terminology now matches Cashier.** On the `subscriptions` table, `name` → `type` and `pelecard_plan` → `pelecard_price`; on `subscription_items`, `pelecard_plan` → `pelecard_price`. The `Billable::newSubscription()` and `Subscription` methods use `type`/price terminology accordingly.
- `Subscription::cancelled()`, `hasPlan()`, and `scopeCancelled()` are **deprecated** aliases of `canceled()`, `hasPrice()`, and `scopeCanceled()` — kept temporarily for backward compatibility.
- `Events\SubscriptionUpdated::$oldPlan` renamed to `$oldPrice`.
- Default payment-method storage now uses the `pm_type` / `pm_last_four` / `pm_exp_month` / `pm_exp_year` columns plus `pelecard_token` (previously wrote to non-existent `card_*` columns); `hasDefaultPaymentMethod()` now keys off `pelecard_token`.
- Bumped dev tooling for the current ecosystem: `orchestra/testbench` (`^10.0|^11.0`), `phpunit/phpunit` (`^12.0`), `phpstan/phpstan` (`^2.0`), `larastan/larastan` (`^3.0`), `rector/rector` (`^2.0`).
- Migrated the test suite from `/** @test */` doc-comment annotations to `#[Test]` attributes (required by PHPUnit 12).
- Removed PHPStan options deprecated in PHPStan 2 (`checkMissingIterableValueType`, `checkGenericClassInNonGenericObjectType`).

### Fixed
- Migration rollback (`down()`) now drops the `users.pelecard_id` index before the column, fixing a `no such column` error on SQLite and other strict drivers.

### Fixed (Pelecard API contract)
Corrected the request wire format and several service names against the official Pelecard "Services ReST API" manual and verified production integrations:
- **Field casing.** `Request::toPelecardFormat()` no longer blindly PascalCases keys. The `/services` REST surface uses camelCase: `terminalNumber`, `total` (amount), `currency` (numeric), `creditCard`, `creditCardDateMmYy` (combined from `expiry_month`/`expiry_year`), `cvv2`, `token`, `paramX`, `paymentsNumber`.
- **Currency** is now sent as a numeric code (ILS=1, USD=2, EUR=978, GBP=826) via a configurable `currency_codes` map, on both the REST and iframe surfaces.
- **Base URL / routing.** Gateway URLs are now host roots; the client routes transaction calls to `/services/<Name>` and lookups to `/PaymentGW/<Name>` (previously the `/services` segment was dropped by URL resolution).
- **Endpoint corrections:** `refund()` and `void()` → `ReverseTransaction` (was the non-existent `CreditTransaction`/`VoidTransaction`); `chargeToken()` → `DebitRegularType` with a `token` field (was the non-existent `ChargeToken`); `getTransaction()`/`getTransactionStatus()` → `/PaymentGW/GetTransaction`; `getErrorMessage()` → `GetErrorMessageHe`, `getErrorMessageEn()` → `GetErrorMessageEn`.
- `capture()` now charges the saved token with ActionType J4 (signature changed to `capture(string $token, int $amount)`); `createToken()` delegates to `convertToToken()` (there is no `CreateToken` service).
- iframe `ActionType` now defaults to **J4** (debit) instead of J2 (registration-only).

> Note: a few services not covered by the available primary sources — `convertToToken` (host/encoding), `initiate3DS`, `get3DSData`, `debitByGooglePay`, `getCompleteTransData` — were left unchanged and should be verified against the official Pelecard Postman collection before production use.

### Upgrading from 1.x
Run `php artisan migrate`. The bundled `..._align_pelecard_with_cashier` migration renames the subscription columns and adds the new ones in place; it is column-guarded, so it is a no-op on fresh installs. Update any application code that referenced the old column names (`name`, `pelecard_plan`) or the deprecated method aliases.

## v1.0.0 - 2025-01-01

### Added
- Initial release
- Cashier-compatible Billable trait
- Multi-tenancy support with encrypted credentials
- Subscription management (create, cancel, resume, swap)
- One-time payment operations (authorize, charge, refund, void, capture)
- **3D Secure authentication** (initiate3DS, get3DSData)
- **Google Pay support** (debitByGooglePay)
- **iFrame integration** (hosted payment pages with Blade component)
- **Type-safe DTOs** (Request objects with validation and IDE autocomplete)
- **Error message retrieval** (getErrorMessage in Hebrew and English)
- **Complete Pelecard API coverage** (64+ services including):
  - Transaction management (abort, delete, complete)
  - EMV operations (contactless, reversal, IntIn)
  - Broadcast operations (Shva, by date, summary)
  - Terminal data (Muhlafim, phone, Sapak number)
  - Ashrait and ParamX validation
  - Track2 and Pelecloud integration
  - Administrative functions
- **Advanced token management** (convertToToken, retrieveToken, updateToken, checkCreditCardForToken)
- **Transaction retrieval** (getTransaction, getCompleteTransData, getTransDataByTrxId)
- **Invoice creation** (ICount, EZCount, Payper)
- **Payment type variations** (debitCreditType, debitPaymentsType, authorizeCreditType, authorizePaymentsType)
- Tokenization for recurring payments
- Webhook support with event dispatching
- Transaction logging
- Payment method management
- Trial period support
- Artisan commands for webhook setup and subscription syncing
- Comprehensive exception handling
- Event system for payment lifecycle
- **Code quality tools** (Laravel Pint, Rector, PHPStan/Larastan)
- Support for Laravel 10.x, 11.x, and 12.x
