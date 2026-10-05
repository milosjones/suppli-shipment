<?php

namespace App\Enums;

enum CargoStatus: string
{
    case Planned = 'planned';
    case InTransit = 'in_transit';
    case Arrived = 'arrived';
    case InStorage = 'in_storage';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned (yet to sail)',
            self::InTransit => 'In transit',
            self::Arrived => 'Arrived',
            self::InStorage => 'In storage',
            self::Delivered => 'Delivered',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Planned => 0,
            self::InTransit => 1,
            self::Arrived => 2,
            self::InStorage => 3,
            self::Delivered => 4,
        };
    }

    public static function rankSql(string $column): string
    {
        return "CASE {$column} ".collect(self::cases())
            ->map(fn (self $status) => "WHEN '{$status->value}' THEN {$status->rank()}")
            ->implode(' ').' END';
    }

    public function nextStatuses(): array
    {
        return match ($this) {
            self::Planned => [self::InTransit],
            self::InTransit => [self::Arrived],
            self::Arrived => [self::InStorage, self::Delivered],
            self::InStorage => [self::Delivered],
            self::Delivered => [],
        };
    }

    public function canMoveTo(self $target): bool
    {
        return in_array($target, $this->nextStatuses(), true);
    }

    public function isBefore(self $other): bool
    {
        return $this->rank() < $other->rank();
    }

    public function canTargetSingleItems(): bool
    {
        return $this === self::InStorage || $this === self::Delivered;
    }

    public function pathTo(self $target): ?array
    {
        if ($this === $target) {
            return [];
        }

        $queue = [[$this, []]];
        $seen = [$this->value => true];

        while ($queue !== []) {
            [$status, $path] = array_shift($queue);

            foreach ($status->nextStatuses() as $next) {
                $nextPath = [...$path, $next];

                if ($next === $target) {
                    return $nextPath;
                }

                if (! isset($seen[$next->value])) {
                    $seen[$next->value] = true;
                    $queue[] = [$next, $nextPath];
                }
            }
        }

        return null;
    }

    public static function leastAdvanced(iterable $statuses): ?self
    {
        $least = null;

        foreach ($statuses as $status) {
            $status = $status instanceof self ? $status : self::from($status);

            if ($least === null || $status->isBefore($least)) {
                $least = $status;
            }
        }

        return $least;
    }

    public static function notArrived(): array
    {
        return [self::Planned, self::InTransit];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
