<?php

namespace App\Enums;

enum WebhookEvent: string
{
    case StatusChanged = 'shipment.status_changed';

    case DatesChanged = 'shipment.dates_changed';

    case Test = 'webhook.test';

    public static function subscribable(): array
    {
        return [self::StatusChanged, self::DatesChanged];
    }

    public function label(): string
    {
        return match ($this) {
            self::StatusChanged => 'Status changed',
            self::DatesChanged => 'Dates changed',
            self::Test => 'Test event',
        };
    }
}
