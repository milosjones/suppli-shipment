<?php

namespace App\Support;

use App\Enums\ShippingMethod;

final class ContainerNumber
{
    private const ISO_PATTERN = '/^[A-Z]{3}[UJZ]\d{7}$/';

    private const AWB_PATTERN = '/^\d{3}-\d{8}$/';

    private const ROAD_PATTERN = '/^[A-Z0-9][A-Z0-9-]{2,19}$/';

    public static function normalize(string $value): string
    {
        $value = strtoupper((string) preg_replace('/[\s\x{00A0}]+/u', '', $value));

        if (preg_match('/^(\d{3})-?(\d{8})$/', $value, $matches) === 1) {
            return $matches[1].'-'.$matches[2];
        }

        return $value;
    }

    public static function isValidFor(ShippingMethod $method, string $value): bool
    {
        $value = self::normalize($value);

        return match ($method) {
            ShippingMethod::Sea => self::isValidIso6346($value),
            ShippingMethod::Air => self::isValidAirWaybill($value),
            ShippingMethod::Road => self::isValidIso6346($value) || preg_match(self::ROAD_PATTERN, $value) === 1,
        };
    }

    public static function isValidIso6346(string $value): bool
    {
        if (preg_match(self::ISO_PATTERN, $value) !== 1) {
            return false;
        }

        return self::iso6346CheckDigit(substr($value, 0, 10)) === (int) $value[10];
    }

    public static function iso6346CheckDigit(string $first10): int
    {
        $sum = 0;

        foreach (str_split($first10) as $position => $char) {
            $value = ctype_digit($char) ? (int) $char : self::letterValue($char);
            $sum += $value * (2 ** $position);
        }

        return $sum % 11 % 10;
    }

    public static function isValidAirWaybill(string $value): bool
    {
        if (preg_match(self::AWB_PATTERN, $value) !== 1) {
            return false;
        }

        $serial = substr($value, 4);

        return ((int) substr($serial, 0, 7)) % 7 === (int) $serial[7];
    }

    public static function detectMethod(string $value): ?ShippingMethod
    {
        $value = self::normalize($value);

        return match (true) {
            preg_match(self::ISO_PATTERN, $value) === 1 => ShippingMethod::Sea,
            preg_match(self::AWB_PATTERN, $value) === 1 => ShippingMethod::Air,
            default => null,
        };
    }

    public static function formatHint(ShippingMethod $method): string
    {
        return match ($method) {
            ShippingMethod::Sea => 'an ISO 6346 container number with a valid check digit, e.g. HLBU8073625',
            ShippingMethod::Air => 'an air waybill number with a valid check digit, e.g. 501-20079076',
            ShippingMethod::Road => 'a container or trailer number (3–20 letters, digits or dashes)',
        };
    }

    private static function letterValue(string $letter): int
    {
        $value = 10;

        foreach (range('A', 'Z') as $candidate) {
            if ($value % 11 === 0) {
                $value++;
            }

            if ($candidate === $letter) {
                return $value;
            }

            $value++;
        }

        return 0;
    }
}
