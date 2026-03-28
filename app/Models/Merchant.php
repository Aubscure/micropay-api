<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Merchant extends Model
{
    use HasFactory, SoftDeletes;

    // UUID primary key — same pattern as Transaction
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'business_name',
        'business_type',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Merchant $merchant) {
            if (empty($merchant->id)) {
                $merchant->id = Str::uuid()->toString();
            }
        });
    }

    // The user who owns this merchant account
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // All transactions received by this merchant
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}