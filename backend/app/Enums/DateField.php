<?php

namespace App\Enums;

enum DateField: string
{
    case Etd = 'etd';
    case Eta = 'eta';
    case CustomerDeliveryDate = 'customer_delivery_date';

    public function label(): string
    {
        return match ($this) {
            self::Etd => 'ETD',
            self::Eta => 'ETA',
            self::CustomerDeliveryDate => 'Customer delivery date',
        };
    }

    public function isShipmentLevel(): bool
    {
        return $this !== self::CustomerDeliveryDate;
    }
}
