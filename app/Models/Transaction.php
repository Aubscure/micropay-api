<?php
// app/Models/Transaction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Tell Laravel to use UUID as the primary key.
     * By default, Laravel expects an auto-incrementing integer.
     */
    protected $keyType = 'string';

    /**
     * Disable auto-incrementing since we use UUIDs.
     */
    public $incrementing = false;

    /**
     * The fields that can be mass-assigned (via create() or fill()).
     * ALWAYS define this — leaving it empty is a security risk.
     */
    protected $fillable = [
        'id',
        'merchant_id',
        'customer_id',
        'amount_centavos',
        'currency',
        'status',
        'payment_method',
        'was_offline',
        'initiated_at',
        'notes',
        'metadata',
    ];

    /**
     * Automatically cast these columns to PHP types.
     * Without casts, everything comes back as a string from the database.
     */
    protected $casts = [
        'amount_centavos' => 'integer',  // Ensure it's always an int
        'was_offline'     => 'boolean',  // Cast to true/false
        'metadata'        => 'array',    // Auto JSON decode/encode
        'initiated_at'    => 'datetime', // Cast to Carbon date object
    ];

    /**
     * Automatically generate a UUID when creating a new transaction.
     * This runs before the model is saved to the database.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Transaction $transaction) {
            // Only generate UUID if one wasn't provided (client-generated UUIDs
            // are allowed for offline transactions)
            if (empty($transaction->id)) {
                $transaction->id = Str::uuid()->toString();
            }
        });
    }

    // ─────────────────────────────────────────────
    // RELATIONSHIPS
    // ─────────────────────────────────────────────

    /**
     * The merchant who receives this payment.
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * The customer who initiated the payment (can be null for anonymous).
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * All fraud flags raised against this transaction.
     */
    public function fraudFlags(): HasMany
    {
        return $this->hasMany(FraudFlag::class);
    }

    // ─────────────────────────────────────────────
    // ACCESSORS (computed properties)
    // ─────────────────────────────────────────────

    /**
     * Get the amount in PHP (not centavos).
     * Usage: $transaction->amount_php → 150.50
     * The 'get' prefix and 'Attribute' suffix are Laravel conventions.
     */
    public function getAmountPhpAttribute(): float
    {
        // Divide centavos by 100 to get the standard currency amount
        return $this->amount_centavos / 100;
    }

    /**
     * Check if this transaction has any unresolved fraud flags.
     */
    public function getIsFlaggedAttribute(): bool
    {
        return $this->fraudFlags()->where('resolved', false)->exists();
    }

    // ─────────────────────────────────────────────
    // SCOPES (reusable query filters)
    // ─────────────────────────────────────────────

    /**
     * Filter only pending transactions.
     * Usage: Transaction::pending()->get()
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Filter transactions created within the last N minutes.
     * Usage: Transaction::recentWindow(30)->get()
     */
    public function scopeRecentWindow($query, int $minutes = 60)
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }
}