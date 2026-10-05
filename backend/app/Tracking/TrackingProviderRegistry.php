<?php

namespace App\Tracking;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class TrackingProviderRegistry
{
    public function __construct(private readonly Container $container) {}

    public function enabled(): array
    {
        return array_map(function (string $name): TrackingProvider {
            $class = config("tracking.map.{$name}") ?? throw new InvalidArgumentException("Unknown tracking provider [{$name}].");

            return $this->container->make($class);
        }, config('tracking.providers', []));
    }
}
