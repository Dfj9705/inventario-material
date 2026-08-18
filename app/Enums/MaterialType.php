<?php

namespace App\Enums;

enum MaterialType: string
{
    case CONSUMABLE = 'consumable';
    case NON_CONSUMABLE = 'manipulativo';

    public function label(): string
    {
        return match ($this) {
            self::CONSUMABLE => 'Consumible',
            self::NON_CONSUMABLE => 'Manipulativo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CONSUMABLE => 'warning',
            self::NON_CONSUMABLE => 'info',
        };
    }

    public static function options(): array
    {
        return [
            self::CONSUMABLE->value => self::CONSUMABLE->label(),
            self::NON_CONSUMABLE->value => self::NON_CONSUMABLE->label(),
        ];
    }
}