<?php

namespace Yousefkadah\Pelecard\Http;

use Yousefkadah\Pelecard\Exceptions\ValidationException;

class Request
{
    /**
     * Explicit map of the package's snake_case input keys to the exact field
     * names the Pelecard /services REST surface expects (camelCase / mixed).
     * Confirmed against the official "Services ReST API" programmer manual and
     * the production WooCommerce gateway plugin.
     *
     * @var array<string, string>
     */
    protected const FIELD_MAP = [
        'terminal' => 'terminalNumber',
        'user' => 'user',
        'password' => 'password',
        'token' => 'token',
        'card_number' => 'creditCard',
        'credit_card' => 'creditCard',
        'credit_card_date_mmyy' => 'creditCardDateMmYy',
        'cvv' => 'cvv2',
        'cvv2' => 'cvv2',
        'amount' => 'total',
        'total' => 'total',
        'new_amount' => 'NewAmount',
        'currency' => 'currency',
        'payments' => 'paymentsNumber',
        'payments_number' => 'paymentsNumber',
        'param_x' => 'paramX',
        'param_z' => 'paramZ',
        'id' => 'id',
        'authorization_number' => 'authorizationNumber',
        'authorization_num' => 'authorizationNumber',
        'shop_number' => 'shopNumber',
        'error_code' => 'ErrorCode',
        'pelecard_transaction_id' => 'debitTrxId',
    ];

    protected array $requiredFields = [];

    /**
     * Create a new request instance.
     */
    public function __construct(protected array $data = []) {}

    /**
     * Set required fields for validation.
     */
    public function setRequiredFields(array $fields): static
    {
        $this->requiredFields = $fields;

        return $this;
    }

    /**
     * Validate the request data.
     */
    public function validate(): void
    {
        foreach ($this->requiredFields as $field) {
            if (! isset($this->data[$field]) || $this->data[$field] === '') {
                throw ValidationException::missingField($field);
            }
        }
    }

    /**
     * Get request data.
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Set a data field.
     */
    public function set(string $key, mixed $value): static
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Get a data field.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Convert to the Pelecard /services REST format.
     *
     * The /services surface uses camelCase field names (terminalNumber, total,
     * currency, creditCard, creditCardDateMmYy, cvv2, ...) — not PascalCase.
     */
    public function toPelecardFormat(): array
    {
        $data = $this->data;

        // Pelecard expects a single MMYY expiry field, not separate month/year.
        if (isset($data['expiry_month']) || isset($data['expiry_year'])) {
            $data['credit_card_date_mmyy'] = $this->formatExpiry(
                $data['expiry_month'] ?? '',
                $data['expiry_year'] ?? ''
            );
            unset($data['expiry_month'], $data['expiry_year']);
        }

        $formatted = [];

        foreach ($data as $key => $value) {
            $formatted[$this->mapFieldName($key)] = $this->formatValue($key, $value);
        }

        return $formatted;
    }

    /**
     * Map a snake_case input key to the Pelecard field name.
     */
    protected function mapFieldName(string $key): string
    {
        return static::FIELD_MAP[$key] ?? $this->toCamelCase($key);
    }

    /**
     * Normalize a value for the Pelecard API (e.g. currency code -> number).
     */
    protected function formatValue(string $key, mixed $value): mixed
    {
        // Pelecard expects a numeric currency code (e.g. ILS => 1), not the ISO letters.
        if ($key === 'currency' && is_string($value) && ! is_numeric($value)) {
            $codes = (array) config('pelecard.currency_codes', []);

            return $codes[strtoupper($value)] ?? $value;
        }

        return $value;
    }

    /**
     * Combine month + year into Pelecard's MMYY expiry format.
     */
    protected function formatExpiry(int|string $month, int|string $year): string
    {
        $month = str_pad((string) $month, 2, '0', STR_PAD_LEFT);
        $year = substr(str_pad((string) $year, 2, '0', STR_PAD_LEFT), -2);

        return $month.$year;
    }

    /**
     * Convert snake_case to camelCase (fallback for unmapped keys).
     */
    protected function toCamelCase(string $string): string
    {
        return lcfirst(str_replace('_', '', ucwords($string, '_')));
    }

    /**
     * Create request from array.
     */
    public static function make(array $data): static
    {
        return new static($data);
    }
}
