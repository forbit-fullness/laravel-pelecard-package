<?php

namespace Yousefkadah\Pelecard;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SubscriptionBuilder
{
    protected int $quantity = 1;

    protected ?Carbon $trialEndsAt = null;

    protected bool $skipTrial = false;

    protected ?Carbon $billingCycleAnchor = null;

    /**
     * The prices the subscription is composed of, keyed by price identifier.
     *
     * @var array<string, array{price: string, product: string|null, quantity: int}>
     */
    protected array $items = [];

    /**
     * Create a new subscription builder instance.
     *
     * @param  string|array<int, string>  $prices
     */
    public function __construct(protected Model $owner, protected string $type, string|array $prices = [])
    {
        foreach ((array) $prices as $price) {
            $this->price($price);
        }
    }

    /**
     * Add a price to the subscription.
     */
    public function price(string $price, int $quantity = 1, ?string $product = null): static
    {
        $this->items[$price] = [
            'price' => $price,
            'product' => $product,
            'quantity' => $quantity,
        ];

        return $this;
    }

    /**
     * Add a price to the subscription.
     *
     * @deprecated Use price() to match Laravel Cashier. Kept for backward compatibility.
     */
    public function add(string $price, int $quantity = 1, ?string $product = null): static
    {
        return $this->price($price, $quantity, $product);
    }

    /**
     * Set the quantity for the subscription or a specific price.
     */
    public function quantity(int $quantity, ?string $price = null): static
    {
        if ($price !== null) {
            $this->items[$price]['quantity'] = $quantity;

            return $this;
        }

        $this->quantity = $quantity;

        if (count($this->items) === 1) {
            $this->items[array_key_first($this->items)]['quantity'] = $quantity;
        }

        return $this;
    }

    /**
     * Set the trial period in days.
     */
    public function trialDays(int $days): static
    {
        $this->trialEndsAt = Carbon::now()->addDays($days);

        return $this;
    }

    /**
     * Set the trial end date.
     */
    public function trialUntil(Carbon $date): static
    {
        $this->trialEndsAt = $date;

        return $this;
    }

    /**
     * Skip the trial period.
     */
    public function skipTrial(): static
    {
        $this->skipTrial = true;
        $this->trialEndsAt = null;

        return $this;
    }

    /**
     * Anchor the billing cycle to a specific date.
     */
    public function anchorBillingCycleOn(Carbon $date): static
    {
        $this->billingCycleAnchor = $date;

        return $this;
    }

    /**
     * Create the subscription.
     */
    public function create(?string $paymentMethod = null, array $options = []): Subscription
    {
        $trialEndsAt = $this->skipTrial ? null : $this->trialEndsAt;

        // If owner has a generic trial, use it.
        if (! $this->skipTrial && ! $trialEndsAt && method_exists($this->owner, 'onGenericTrial') && $this->owner->onGenericTrial()) {
            $trialEndsAt = $this->owner->trial_ends_at;
        }

        $isSinglePrice = count($this->items) <= 1;
        $firstItem = $this->items[array_key_first($this->items)] ?? null;

        $subscription = $this->owner->subscriptions()->create([
            'type' => $this->type,
            'pelecard_price' => $isSinglePrice ? ($firstItem['price'] ?? null) : null,
            'quantity' => $isSinglePrice ? ($firstItem['quantity'] ?? $this->quantity) : null,
            'pelecard_status' => $trialEndsAt ? Subscription::STATUS_TRIALING : Subscription::STATUS_ACTIVE,
            'trial_ends_at' => $trialEndsAt,
        ]);

        // For multi-price subscriptions, the prices live in subscription_items.
        if (! $isSinglePrice) {
            foreach ($this->items as $item) {
                $subscription->items()->create([
                    'pelecard_price' => $item['price'],
                    'pelecard_product' => $item['product'],
                    'quantity' => $item['quantity'],
                ]);
            }
        }

        // If a payment method was provided and we are not on trial, store it as
        // the default payment method for recurring billing.
        if ($paymentMethod && ! $trialEndsAt && method_exists($this->owner, 'updateDefaultPaymentMethod')) {
            $this->owner->updateDefaultPaymentMethod($paymentMethod);
        }

        event(new Events\SubscriptionCreated($subscription));

        return $subscription;
    }
}
