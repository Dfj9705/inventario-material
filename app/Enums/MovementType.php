<?php

namespace App\Enums;

enum MovementType: string
{
    case ENTRY = 'entry';
    case CONSUMPTION = 'consumption';
    case LOAN = 'loan';
    case RETURN = 'return';
    case ADJUSTMENT_IN = 'adjustment_in';
    case ADJUSTMENT_OUT = 'adjustment_out';
    case TRANSFER_IN = 'transfer_in';
    case TRANSFER_OUT = 'transfer_out';

    public function label(): string
    {
        return match ($this) {
            self::ENTRY => 'Entrada',
            self::CONSUMPTION => 'Consumo',
            self::LOAN => 'Préstamo',
            self::RETURN => 'Devolución',
            self::ADJUSTMENT_IN => 'Ajuste positivo',
            self::ADJUSTMENT_OUT => 'Ajuste negativo',
            self::TRANSFER_IN => 'Entrada por transferencia',
            self::TRANSFER_OUT => 'Salida por transferencia',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ENTRY,
            self::RETURN ,
            self::ADJUSTMENT_IN,
            self::TRANSFER_IN => 'success',

            self::CONSUMPTION,
            self::LOAN,
            self::ADJUSTMENT_OUT,
            self::TRANSFER_OUT => 'danger',
        };
    }

    public function factor(): int
    {
        return match ($this) {
            self::ENTRY,
            self::RETURN ,
            self::ADJUSTMENT_IN,
            self::TRANSFER_IN => 1,

            self::CONSUMPTION,
            self::LOAN,
            self::ADJUSTMENT_OUT,
            self::TRANSFER_OUT => -1,
        };
    }

    public function isIncoming(): bool
    {
        return $this->factor() === 1;
    }

    public function isOutgoing(): bool
    {
        return $this->factor() === -1;
    }
}