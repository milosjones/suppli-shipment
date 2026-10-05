<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class Dates
{
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('shipments.timezone'))->startOfDay();
    }

    public static function daysBetween(?CarbonInterface $from, ?CarbonInterface $to): int
    {
        if ($from === null || $to === null) {
            return 0;
        }

        return (int) round(
            CarbonImmutable::parse($from->toDateString())->diffInDays(CarbonImmutable::parse($to->toDateString()), false)
        );
    }

    public static function parse(?string $date): ?CarbonImmutable
    {
        return $date === null || $date === '' ? null : CarbonImmutable::parse($date)->startOfDay();
    }

    public static function eventTime(?string $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
            ? CarbonImmutable::parse("{$value} 12:00", config('shipments.timezone'))
            : CarbonImmutable::parse($value);
    }

    public static function sameDay(?CarbonInterface $a, ?CarbonInterface $b): bool
    {
        return $a?->toDateString() === $b?->toDateString();
    }
}
