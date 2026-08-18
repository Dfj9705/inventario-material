<?php

namespace App\Models;

use App\Enums\LoanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'person_id',
        'warehouse_id',
        'loan_date',
        'expected_return_date',
        'signature_path',
        'observations',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'loan_date' => 'datetime',
            'expected_return_date' => 'date',
            'status' => LoanStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Loan $loan): void {
            if (filled($loan->code)) {
                return;
            }

            do {
                $code = sprintf(
                    'PRE-%s-%s',
                    now()->format('Ymd'),
                    Str::upper(Str::random(6))
                );
            } while (self::query()->where('code', $code)->exists());

            $loan->code = $code;
        });
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LoanItem::class);
    }

    public function isOverdue(): bool
    {
        return $this->status !== LoanStatus::RETURNED
            && $this->expected_return_date !== null
            && $this->expected_return_date->isBefore(today());
    }

    public function loanReturns(): HasMany
    {
        return $this->hasMany(LoanReturn::class);
    }
}