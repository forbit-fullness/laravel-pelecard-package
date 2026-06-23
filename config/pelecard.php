<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billable Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model that owns subscriptions, transactions and the saved
    | card token (i.e. the model that uses the Billable trait). Defaults to the
    | application's User model, but may point at any model — e.g. a Tenant or
    | Team for SaaS billing. The model's table and foreign key (via
    | getTable()/getForeignKey()) drive the package migration and relationships,
    | so a Tenant model with table "tenants" yields a "tenant_id" foreign key.
    |
    */

    'model' => env('PELECARD_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy Configuration
    |--------------------------------------------------------------------------
    |
    | Enable multi-tenant mode to support multiple Pelecard accounts in the
    | same application. When enabled, credentials are resolved per tenant.
    |
    */

    'multi_tenant' => env('PELECARD_MULTI_TENANT', false),

    /*
    |--------------------------------------------------------------------------
    | Credentials Resolver
    |--------------------------------------------------------------------------
    |
    | Custom callback to resolve tenant-specific credentials. Receives the
    | billable entity and should return a PelecardCredentials instance.
    |
    | Example: fn($billable) => $billable->team->pelecardCredentials
    |
    */

    'credentials_resolver' => null,

    /*
    |--------------------------------------------------------------------------
    | Tenant Column
    |--------------------------------------------------------------------------
    |
    | Column name used for tenant identification in multi-tenant mode.
    |
    */

    'tenant_column' => env('PELECARD_TENANT_COLUMN', 'team_id'),

    /*
    |--------------------------------------------------------------------------
    | Default API Credentials
    |--------------------------------------------------------------------------
    |
    | Default Pelecard API credentials. Used when multi-tenancy is disabled
    | or as fallback credentials.
    |
    */

    'terminal' => env('PELECARD_TERMINAL'),
    'user' => env('PELECARD_USER'),
    'password' => env('PELECARD_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | Pelecard environment: 'sandbox' or 'production'
    |
    */

    'environment' => env('PELECARD_ENV', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Gateway URLs
    |--------------------------------------------------------------------------
    |
    | Pelecard host roots. The client appends the endpoint family per call:
    | transaction services live under /services/<Name> and a few utility calls
    | (GetTransaction, ValidateByUniqueKey, init) under /PaymentGW/<Name>.
    | gateway20 and gateway21 are equivalent production hosts; there is no
    | separate sandbox host — Pelecard issues test-terminal credentials against
    | the same host.
    |
    */

    'gateway_urls' => [
        'sandbox' => 'https://gateway20.pelecard.biz',
        'production' => 'https://gateway21.pelecard.biz',
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Default currency for transactions (ISO 4217 code).
    |
    */

    'currency' => env('PELECARD_CURRENCY', 'ILS'),

    /*
    |--------------------------------------------------------------------------
    | Currency Codes
    |--------------------------------------------------------------------------
    |
    | Pelecard expects the currency as a numeric code, not an ISO letter code.
    | The string currency above is converted to its numeric code via this map
    | before being sent to the API. ILS => 1 is confirmed by Pelecard; verify
    | the foreign-currency codes against your terminal before relying on them.
    |
    */

    'currency_codes' => [
        'ILS' => 1,
        'USD' => 2,
        'EUR' => 978,
        'GBP' => 826,
    ],

    /*
    |--------------------------------------------------------------------------
    | Language
    |--------------------------------------------------------------------------
    |
    | Default language for Pelecard responses: 'he' (Hebrew) or 'en' (English)
    |
    */

    'language' => env('PELECARD_LANGUAGE', 'he'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for webhook handling and signature validation.
    |
    */

    'webhook' => [
        'enabled' => env('PELECARD_WEBHOOK_ENABLED', true),
        'path' => env('PELECARD_WEBHOOK_PATH', 'pelecard/webhook'),
        'signature_validation' => env('PELECARD_WEBHOOK_SIGNATURE_VALIDATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable logging of API requests and responses for debugging.
    |
    */

    'logging' => [
        'enabled' => env('PELECARD_LOGGING_ENABLED', false),
        'channel' => env('PELECARD_LOGGING_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Cache settings for credentials and API responses.
    |
    */

    'cache' => [
        'enabled' => env('PELECARD_CACHE_ENABLED', true),
        'ttl' => env('PELECARD_CACHE_TTL', 3600), // 1 hour
        'prefix' => 'pelecard',
    ],

];
