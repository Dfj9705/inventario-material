<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class LoanReturn extends Model
{
    protected $fillable = [
        'loan_id',
        'return_date',
        'observations',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(LoanReturnItem::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    protected static function booted(): void
    {
        static::updating(
            fn() => throw new LogicException(
                'Las devoluciones no pueden modificarse.'
            )
        );

        static::deleting(
            fn() => throw new LogicException(
                'Las devoluciones no pueden eliminarse.'
            )
        );
    }
}