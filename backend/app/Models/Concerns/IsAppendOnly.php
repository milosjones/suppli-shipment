<?php

namespace App\Models\Concerns;

use LogicException;

trait IsAppendOnly
{
    public static function bootIsAppendOnly(): void
    {
        static::updating(fn () => throw new LogicException(static::class.' records are append-only.'));
        static::deleting(fn () => throw new LogicException(static::class.' records are append-only.'));
    }
}
