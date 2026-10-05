<?php

namespace App\Enums;

enum ShippingMethod: string
{
    case Sea = 'sea';
    case Air = 'air';
    case Road = 'road';

    public function label(): string
    {
        return match ($this) {
            self::Sea => 'Sea',
            self::Air => 'Air',
            self::Road => 'Road',
        };
    }

    public function unitLabel(): string
    {
        return match ($this) {
            self::Sea => 'Container no',
            self::Air => 'AWB no',
            self::Road => 'Trailer / container no',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
