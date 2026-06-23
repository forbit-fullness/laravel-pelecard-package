<?php

namespace Yousefkadah\Pelecard;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $subscription_id
 * @property string|null $pelecard_product
 * @property string $pelecard_price
 * @property int $quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SubscriptionItem extends Model
{
    protected $table = 'subscription_items';

    protected $fillable = [
        'subscription_id',
        'pelecard_product',
        'pelecard_price',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Get the subscription that owns the item.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Determine if the item is for the given price.
     */
    public function hasPrice(string $price): bool
    {
        return $this->pelecard_price === $price;
    }

    /**
     * Increment the quantity.
     */
    public function incrementQuantity(int $count = 1): static
    {
        return $this->updateQuantity($this->quantity + $count);
    }

    /**
     * Decrement the quantity.
     */
    public function decrementQuantity(int $count = 1): static
    {
        return $this->updateQuantity(max(1, $this->quantity - $count));
    }

    /**
     * Update the quantity.
     */
    public function updateQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        $this->save();

        return $this;
    }
}
