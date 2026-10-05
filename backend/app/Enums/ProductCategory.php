<?php

namespace App\Enums;

enum ProductCategory: string
{
    case NetPen = 'net_pen';
    case DeadFishCollector = 'dead_fish_collector';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NetPen => 'Net pen',
            self::DeadFishCollector => 'Dead fish collector',
            self::Other => 'Other',
        };
    }

    public function code(): ?string
    {
        return match ($this) {
            self::NetPen => 'NP',
            self::DeadFishCollector => 'DF',
            self::Other => null,
        };
    }

    public static function infer(?string $netType, ?string $description): self
    {
        $netType = mb_strtolower(trim((string) $netType));
        $description = mb_strtoupper(trim((string) $description));

        if (in_array($netType, ['dfc', 'dead fish collector'], true) || str_starts_with($description, 'DFC')) {
            return self::DeadFishCollector;
        }

        if (in_array($netType, ['net cage', 'net pen', 'np base'], true)
            || str_starts_with($description, 'NP-')
            || str_starts_with($description, 'NC-')) {
            return self::NetPen;
        }

        return self::Other;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
