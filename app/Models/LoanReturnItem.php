<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use LogicException;

class LoanReturnItem extends Model
{
    protected $fillable = [
        'loan_return_id',
        'loan_item_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    public function loanReturn(): BelongsTo
    {
        return $this->belongsTo(LoanReturn::class);
    }

    public function loanItem(): BelongsTo
    {
        return $this->belongsTo(LoanItem::class);
    }

    public function inventoryMovements(): MorphMany
    {
        return $this->morphMany(
            InventoryMovement::class,
            'reference'
        );
    }

    protected static function booted(): void
    {
        static::updating(
            fn() => throw new LogicException(
                'Los detalles de devolución no pueden modificarse.'
            )
        );

        static::deleting(
            fn() => throw new LogicException(
                'Los detalles de devolución no pueden eliminarse.'
            )
        );
    }
}