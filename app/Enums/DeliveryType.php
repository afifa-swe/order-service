<?php

namespace App\Enums;

enum DeliveryType: string
{
    case Pickup = 'pickup';
    case Address = 'address';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
