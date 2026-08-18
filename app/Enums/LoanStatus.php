<?php

namespace App\Enums;

enum LoanStatus: string
{
    case ACTIVE = 'active';
    case PARTIALLY_RETURNED = 'partially_returned';
    case RETURNED = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Prestado',
            self::PARTIALLY_RETURNED => 'Devolución parcial',
            self::RETURNED => 'Devuelto',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'warning',
            self::PARTIALLY_RETURNED => 'info',
            self::RETURNED => 'success',
        };
    }
}