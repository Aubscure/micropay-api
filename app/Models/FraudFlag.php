<?php
// app/Models/FraudFlag.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FraudFlag extends Model
{
    // UUID primary key
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'transaction_id',
        'rule_triggered',
        'risk_score',
        'source',
        'reason',
        'resolved',
        'resolved_at',
    ];

    protected $casts = [
        'risk_score'  => 'decimal:3',
        'resolved'    => 'boolean',
        'resolved_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (FraudFlag $flag) {
            if (empty($flag->id)) {
                $flag->id = Str::uuid()->toString();
            }
        });
    }

    /**
     * The transaction this flag belongs to.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}