<?php

namespace Yousefkadah\Pelecard;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Yousefkadah\Pelecard\Events\CardSaved;
use Yousefkadah\Pelecard\Events\PaymentFailed;
use Yousefkadah\Pelecard\Events\PaymentSucceeded;
use Yousefkadah\Pelecard\Helpers\TokenExtractor;
use Yousefkadah\Pelecard\Http\Response;

trait Billable
{
    /**
     * Get all subscriptions for the billable entity.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, $this->getForeignKey())->orderBy('created_at', 'desc');
    }

    /**
     * Get a subscription by type.
     */
    public function subscription(string $type = 'default'): ?Subscription
    {
        return $this->subscriptions()->where('type', $type)->first();
    }

    /**
     * Check if the billable entity is subscribed to the given type (optionally to a price).
     */
    public function subscribed(string $type = 'default', ?string $price = null): bool
    {
        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        if ($price) {
            return $subscription->hasPrice($price);
        }

        return true;
    }

    /**
     * Check if the billable entity is subscribed to the given price(s).
     *
     * @param  string|array<int, string>  $prices
     */
    public function subscribedToPrice(string|array $prices, string $type = 'default'): bool
    {
        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        foreach ((array) $prices as $price) {
            if ($subscription->hasPrice($price)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the billable entity is subscribed to the given product(s).
     *
     * @param  string|array<int, string>  $products
     */
    public function subscribedToProduct(string|array $products, string $type = 'default'): bool
    {
        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        foreach ((array) $products as $product) {
            if ($subscription->hasProduct($product)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the billable entity is on trial for the given type (optionally a price).
     */
    public function onTrial(string $type = 'default', ?string $price = null): bool
    {
        if (func_num_args() === 0 && $this->onGenericTrial()) {
            return true;
        }

        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->onTrial()) {
            return false;
        }

        return $price ? $subscription->hasPrice($price) : true;
    }

    /**
     * Check if the billable entity is on a generic trial.
     */
    public function onGenericTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if the billable entity has an incomplete payment for the given type.
     */
    public function hasIncompletePayment(string $type = 'default'): bool
    {
        $subscription = $this->subscription($type);

        return $subscription ? $subscription->hasIncompletePayment() : false;
    }

    /**
     * Create a new subscription.
     *
     * @param  string|array<int, string>  $prices
     */
    public function newSubscription(string $type, string|array $prices = []): SubscriptionBuilder
    {
        return new SubscriptionBuilder($this, $type, $prices);
    }

    /**
     * Get Pelecard credentials for this billable entity.
     */
    public function pelecardCredentials(): ?PelecardCredentials
    {
        if (config('pelecard.multi_tenant')) {
            $resolver = app(CredentialsResolver::class);

            return $resolver->resolve($this);
        }

        return null;
    }

    /**
     * Get the Pelecard client for this billable entity.
     */
    public function pelecardClient(): PelecardClient
    {
        return PelecardClient::for($this);
    }

    /**
     * Charge the billable entity and return the response.
     */
    public function charge(int $amount, string $description, array $options = []): Response
    {
        $client = PelecardClient::for($this);

        $data = array_merge([
            'amount' => $amount,
            'description' => $description,
            'currency' => config('pelecard.currency', 'ILS'),
        ], $options);

        $response = $client->charge($data);

        // Log the transaction
        $this->pelecardTransactions()->create([
            'type' => 'charge',
            'amount' => $amount,
            'currency' => $data['currency'],
            'pelecard_transaction_id' => $response->getTransactionId(),
            'status' => $response->isSuccessful() ? 'successful' : 'failed',
            'response' => $response->toArray(),
        ]);

        if ($response->isSuccessful()) {
            event(new PaymentSucceeded($this, $response));
        } else {
            event(new PaymentFailed($this, $response));
        }

        return $response;
    }

    /**
     * Charge the billable entity and automatically save the card token.
     * This method extracts the token from the payment response (J2/J4/J5)
     * and saves it as the default payment method.
     */
    public function chargeAndSaveCard(int $amount, string $description, array $cardDetails = []): Response
    {
        $client = PelecardClient::for($this);

        $data = array_merge([
            'amount' => $amount,
            'description' => $description,
            'currency' => config('pelecard.currency', 'ILS'),
        ], $cardDetails);

        // Make the payment (J2/J4/J5 will return a token)
        $response = $client->charge($data);

        // Log the transaction
        $this->pelecardTransactions()->create([
            'type' => 'charge',
            'amount' => $amount,
            'currency' => $data['currency'],
            'pelecard_transaction_id' => $response->getTransactionId(),
            'status' => $response->isSuccessful() ? 'successful' : 'failed',
            'response' => $response->toArray(),
        ]);

        // If successful, extract and save the token
        if ($response->isSuccessful()) {
            $token = $response->get('Token');

            if ($token) {
                $this->updateDefaultPaymentMethod($token, [
                    'type' => 'card',
                    'last_four' => $response->get('Last4Digits') ?? substr($cardDetails['card_number'] ?? '', -4),
                    'brand' => $response->get('CardBrand') ?? 'unknown',
                    'exp_month' => $cardDetails['expiry_month'] ?? null,
                    'exp_year' => $cardDetails['expiry_year'] ?? null,
                ]);
            }

            event(new PaymentSucceeded($this, $response));
        } else {
            event(new PaymentFailed($this, $response));
        }

        return $response;
    }

    /**
     * Refund a transaction.
     */
    public function refund(string $transactionId, int $amount): Response
    {
        $client = $this->pelecardClient();
        $response = $client->refund($transactionId, $amount);

        $this->logTransaction('refund', $response);

        return $response;
    }

    /**
     * Update the default payment method from a token.
     */
    public function updateDefaultPaymentMethod(string $token, array $cardDetails = []): void
    {
        $this->forceFill([
            'pelecard_token' => $token,
            'pm_type' => $cardDetails['brand'] ?? null,
            'pm_last_four' => $cardDetails['last_four'] ?? null,
            'pm_exp_month' => $cardDetails['exp_month'] ?? null,
            'pm_exp_year' => $cardDetails['exp_year'] ?? null,
        ])->save();

        event(new CardSaved($this, $token, $cardDetails));
    }

    /**
     * Update the default payment method from a payment response.
     * Automatically extracts token and card details from the response.
     */
    public function updateDefaultPaymentMethodFromResponse(Response $response): bool
    {
        $token = TokenExtractor::extractToken($response);

        if (! $token) {
            return false;
        }

        $cardDetails = TokenExtractor::extractCardDetails($response);
        $this->updateDefaultPaymentMethod($token, $cardDetails);

        return true;
    }

    /**
     * Get the default payment method.
     */
    public function defaultPaymentMethod(): ?PaymentMethod
    {
        if (! $this->hasDefaultPaymentMethod()) {
            return null;
        }

        return new PaymentMethod([
            'token' => $this->pelecard_token,
            'type' => $this->pm_type,
            'last_four' => $this->pm_last_four,
            'brand' => $this->pm_type,
            'expiry_month' => $this->pm_exp_month,
            'expiry_year' => $this->pm_exp_year,
        ]);
    }

    /**
     * Check if the billable entity has a default payment method.
     */
    public function hasDefaultPaymentMethod(): bool
    {
        return ! is_null($this->pelecard_token);
    }

    /**
     * Delete the default payment method.
     */
    public function deletePaymentMethod(): void
    {
        $this->forceFill([
            'pelecard_token' => null,
            'pm_type' => null,
            'pm_last_four' => null,
            'pm_exp_month' => null,
            'pm_exp_year' => null,
        ])->save();
    }

    /**
     * Get all invoices for the billable entity.
     */
    public function invoices(): array
    {
        // This would integrate with Pelecard's invoice API
        // For now, return empty array
        return [];
    }

    /**
     * Find a specific invoice.
     */
    public function findInvoice(string $id): ?Invoice
    {
        // This would retrieve invoice from Pelecard
        return null;
    }

    /**
     * Download an invoice as PDF.
     */
    public function downloadInvoice(string $id, array $data = []): string
    {
        // This would generate/download invoice PDF
        return '';
    }

    /**
     * Log a transaction for this billable entity.
     */
    protected function logTransaction(string $type, Response $response): void
    {
        if (! $response->successful()) {
            return;
        }

        $this->pelecardTransactions()->create([
            'pelecard_transaction_id' => $response->getTransactionId(),
            'type' => $type,
            'amount' => $response->get('Amount') ?? $response->get('amount'),
            'currency' => $response->get('Currency') ?? config('pelecard.currency'),
            'status' => $response->successful() ? 'completed' : 'failed',
            'metadata' => $response->getData(),
        ]);
    }

    /**
     * Get all transactions for this billable entity.
     */
    public function pelecardTransactions(): HasMany
    {
        return $this->hasMany(PelecardTransaction::class, $this->getForeignKey());
    }

    /**
     * Get the billable entity's Pelecard ID.
     */
    public function pelecardId(): ?string
    {
        return $this->pelecard_id;
    }

    /**
     * Set the billable entity's Pelecard ID.
     */
    public function setPelecardId(string $id): void
    {
        $this->pelecard_id = $id;
        $this->save();
    }
}
