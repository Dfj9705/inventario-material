<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'material_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'returned_quantity' => 'decimal:3',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function pendingQuantity(): float
    {
        return round(
            (float) $this->quantity - (float) $this->returned_quantity,
            3
        );
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(LoanReturnItem::class);
    }
}