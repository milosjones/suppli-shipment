<?php

namespace App\Support;

final class TagNumber
{
    public const DEFAULT_PREFIX = 'GNP';

    public static function normalize(string|int|float|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            $value = (string) (int) $value;
        }

        $value = strtoupper(trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $value)));

        if ($value === '' || trim($value, ' -') === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return self::DEFAULT_PREFIX.'-'.$value;
        }

        if (preg_match('/^([A-Z]{2,4})\s*-?\s*(\d+)$/', $value, $matches) === 1) {
            return $matches[1].'-'.$matches[2];
        }

        return $value;
    }

    public static function key(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $key = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $value));

        return $key === '' ? null : $key;
    }
}
