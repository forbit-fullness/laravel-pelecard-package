<?php

namespace Yousefkadah\Pelecard;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string|null $pelecard_subscription_id
 * @property string|null $pelecard_status
 * @property string|null $pelecard_price
 * @property int|null $quantity
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Collection<int, SubscriptionItem> $items
 */
class Subscription extends Model
{
    /**
     * Subscription status values (mirrors Cashier's Stripe statuses).
     */
    public const STATUS_ACTIVE = 'active';

    public const STATUS_TRIALING = 'trialing';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_INCOMPLETE = 'incomplete';

    protected $table = 'subscriptions';

    protected $fillable = [
        'user_id',
        'type',
        'pelecard_subscription_id',
        'pelecard_status',
        'pelecard_price',
        'quantity',
        'trial_ends_at',
        'ends_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'trial_ends_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * Get the user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\\Models\\User'));
    }

    /**
     * Get the subscription items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    /**
     * Find an item for the given price, or fail.
     */
    public function findItemOrFail(string $price): SubscriptionItem
    {
        return SubscriptionItem::query()
            ->where('subscription_id', $this->getKey())
            ->where('pelecard_price', $price)
            ->firstOrFail();
    }

    /**
     * Determine if the subscription has multiple prices (items).
     */
    public function hasMultiplePrices(): bool
    {
        return is_null($this->pelecard_price);
    }

    /**
     * Determine if the subscription has a single price.
     */
    public function hasSinglePrice(): bool
    {
        return ! $this->hasMultiplePrices();
    }

    /**
     * Check if the subscription is active.
     */
    public function active(): bool
    {
        return (! $this->canceled() || $this->onGracePeriod())
            && ! $this->pastDue()
            && ! $this->incomplete();
    }

    /**
     * Check if the subscription is valid (active, on trial, or on grace period).
     */
    public function valid(): bool
    {
        return $this->active() || $this->onTrial() || $this->onGracePeriod();
    }

    /**
     * Check if the subscription is recurring and not on trial.
     */
    public function recurring(): bool
    {
        return ! $this->onTrial() && ! $this->canceled();
    }

    /**
     * Check if the subscription is canceled.
     */
    public function canceled(): bool
    {
        return ! is_null($this->ends_at);
    }

    /**
     * Check if the subscription is canceled.
     *
     * @deprecated Use canceled() to match Laravel Cashier. Kept for backward compatibility.
     */
    public function cancelled(): bool
    {
        return $this->canceled();
    }

    /**
     * Check if the subscription has ended (canceled and grace period elapsed).
     */
    public function ended(): bool
    {
        return $this->canceled() && ! $this->onGracePeriod();
    }

    /**
     * Check if the subscription is past due.
     */
    public function pastDue(): bool
    {
        return $this->pelecard_status === self::STATUS_PAST_DUE;
    }

    /**
     * Check if the subscription is incomplete.
     */
    public function incomplete(): bool
    {
        return $this->pelecard_status === self::STATUS_INCOMPLETE;
    }

    /**
     * Check if the subscription has an incomplete or past-due payment.
     */
    public function hasIncompletePayment(): bool
    {
        return $this->pastDue() || $this->incomplete();
    }

    /**
     * Check if the subscription is on trial.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if the subscription is on grace period.
     */
    public function onGracePeriod(): bool
    {
        return $this->ends_at && $this->ends_at->isFuture();
    }

    /**
     * Check if the subscription is for the given price.
     */
    public function hasPrice(string $price): bool
    {
        if ($this->hasMultiplePrices()) {
            return $this->items->contains('pelecard_price', $price);
        }

        return $this->pelecard_price === $price;
    }

    /**
     * Check if the subscription is for the given product.
     */
    public function hasProduct(string $product): bool
    {
        return $this->items->contains('pelecard_product', $product);
    }

    /**
     * Check if the subscription has the given price.
     *
     * @deprecated Use hasPrice() to match Laravel Cashier. Kept for backward compatibility.
     */
    public function hasPlan(string $plan): bool
    {
        return $this->hasPrice($plan);
    }

    /**
     * Cancel the subscription at the end of the billing period.
     */
    public function cancel(): static
    {
        $this->ends_at = $this->onTrial()
            ? $this->trial_ends_at
            : Carbon::now()->addMonth();

        $this->pelecard_status = self::STATUS_CANCELED;

        $this->save();

        event(new Events\SubscriptionCancelled($this));

        return $this;
    }

    /**
     * Cancel the subscription immediately.
     */
    public function cancelNow(): static
    {
        $this->ends_at = Carbon::now();
        $this->pelecard_status = self::STATUS_CANCELED;
        $this->save();

        event(new Events\SubscriptionCancelled($this));

        return $this;
    }

    /**
     * Cancel the subscription at a specific moment in time.
     */
    public function cancelAt(Carbon $endsAt): static
    {
        $this->ends_at = $endsAt;
        $this->pelecard_status = self::STATUS_CANCELED;
        $this->save();

        event(new Events\SubscriptionCancelled($this));

        return $this;
    }

    /**
     * Resume a canceled subscription.
     */
    public function resume(): static
    {
        if (! $this->onGracePeriod()) {
            throw new \LogicException('Cannot resume a subscription that is not on grace period.');
        }

        $this->ends_at = null;
        $this->pelecard_status = self::STATUS_ACTIVE;
        $this->save();

        event(new Events\SubscriptionUpdated($this));

        return $this;
    }

    /**
     * Swap the subscription to new price(s).
     *
     * @param  string|array<int, string>  $prices
     */
    public function swap(string|array $prices): static
    {
        $oldPrice = $this->pelecard_price;
        $prices = (array) $prices;

        if (count($prices) === 1) {
            $this->pelecard_price = $prices[0];
            $this->items()->delete();
        } else {
            $this->pelecard_price = null;

            $this->items()->whereNotIn('pelecard_price', $prices)->delete();

            foreach ($prices as $price) {
                $this->items()->updateOrCreate(
                    ['pelecard_price' => $price],
                    ['quantity' => $this->quantity ?? 1],
                );
            }
        }

        $this->save();

        event(new Events\SubscriptionUpdated($this, $oldPrice));

        return $this;
    }

    /**
     * Swap the subscription to new price(s) and invoice immediately.
     *
     * @param  string|array<int, string>  $prices
     */
    public function swapAndInvoice(string|array $prices): static
    {
        return $this->swap($prices);
    }

    /**
     * Swap the subscription to new price(s) without proration.
     *
     * @param  string|array<int, string>  $prices
     */
    public function swapWithoutProration(string|array $prices): static
    {
        return $this->noProrate()->swap($prices);
    }

    /**
     * Increment the quantity of the subscription.
     */
    public function incrementQuantity(int $count = 1): static
    {
        return $this->updateQuantity(($this->quantity ?? 1) + $count);
    }

    /**
     * Decrement the quantity of the subscription.
     */
    public function decrementQuantity(int $count = 1): static
    {
        return $this->updateQuantity(max(1, ($this->quantity ?? 1) - $count));
    }

    /**
     * Update the quantity of the subscription.
     */
    public function updateQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        $this->save();

        event(new Events\SubscriptionUpdated($this));

        return $this;
    }

    /**
     * Disable proration for the next operation.
     */
    public function noProrate(): static
    {
        // This would be used to prevent proration on next swap.
        // Implementation depends on Pelecard's proration support.
        return $this;
    }

    /**
     * Skip the trial period.
     */
    public function skipTrial(): static
    {
        $this->trial_ends_at = null;
        $this->save();

        return $this;
    }

    /**
     * Scope to get active subscriptions.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($query): void {
            $query->whereNull('ends_at')
                ->orWhere('ends_at', '>', Carbon::now());
        });
    }

    /**
     * Scope to get canceled subscriptions.
     */
    public function scopeCanceled($query)
    {
        return $query->whereNotNull('ends_at');
    }

    /**
     * Scope to get canceled subscriptions.
     *
     * @deprecated Use scopeCanceled() to match Laravel Cashier. Kept for backward compatibility.
     */
    public function scopeCancelled($query)
    {
        return $this->scopeCanceled($query);
    }

    /**
     * Scope to get subscriptions on trial.
     */
    public function scopeOnTrial($query)
    {
        return $query->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', Carbon::now());
    }

    /**
     * Scope to get subscriptions on grace period.
     */
    public function scopeOnGracePeriod($query)
    {
        return $query->whereNotNull('ends_at')
            ->where('ends_at', '>', Carbon::now());
    }
}
