<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use LogicException;

class InventoryTransfer extends Model
{
    protected $fillable = [
        'code',
        'material_id',
        'source_warehouse_id',
        'destination_warehouse_id',
        'quantity',
        'transfer_date',
        'observations',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'transfer_date' => 'datetime',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class,
            'source_warehouse_id'
        );
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class,
            'destination_warehouse_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function movements(): MorphMany
    {
        return $this->morphMany(
            InventoryMovement::class,
            'reference'
        );
    }

    protected static function booted(): void
    {
        static::creating(function (InventoryTransfer $transfer): void {
            $transfer->code ??= sprintf(
                'TRA-%s-%s',
                now()->format('Ymd'),
                Str::upper(Str::random(6))
            );
        });

        static::updating(
            fn() => throw new LogicException(
                'Las transferencias no pueden modificarse.'
            )
        );

        static::deleting(
            fn() => throw new LogicException(
                'Las transferencias no pueden eliminarse.'
            )
        );
    }
}