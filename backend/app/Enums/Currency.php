<?php

namespace App\Enums;

enum Currency: string
{
    case USD = 'USD';
    case KHR = 'KHR';

    public function minorUnitDecimals(): int
    {
        return match ($this) {
            self::USD => 2,
            self::KHR => 0,
        };
    }

    public function minorUnitDivisor(): int
    {
        return match ($this) {
            self::USD => 100,
            self::KHR => 1,
        };
    }
}
