<?php

namespace Yousefkadah\Pelecard;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $pelecard_transaction_id
 * @property string $type
 * @property int $amount
 * @property string $currency
 * @property string $status
 * @property array $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PelecardTransaction extends Model
{
    protected $table = 'pelecard_transactions';

    protected $fillable = [
        'pelecard_transaction_id',
        'type',
        'amount',
        'currency',
        'status',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the mass-assignable attributes, including the billable foreign key.
     *
     * @return array<int, string>
     */
    public function getFillable(): array
    {
        $model = config('pelecard.model', 'App\\Models\\User');

        return array_merge([(new $model)->getForeignKey()], $this->fillable);
    }

    /**
     * Get the billable owner of the transaction (user, tenant, team, ...).
     */
    public function owner(): BelongsTo
    {
        $model = config('pelecard.model', 'App\\Models\\User');

        return $this->belongsTo($model, (new $model)->getForeignKey());
    }

    /**
     * Get the owner of the transaction.
     *
     * @deprecated Use owner() — kept for backward compatibility.
     */
    public function user(): BelongsTo
    {
        return $this->owner();
    }

    /**
     * Check if the transaction was successful.
     */
    public function successful(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the transaction failed.
     */
    public function failed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Scope to get successful transactions.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get failed transactions.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get transactions of a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
