<?php

namespace App\Enums;

enum PaymentType: string
{
    case Card = 'card';
    case Credit = 'credit';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
