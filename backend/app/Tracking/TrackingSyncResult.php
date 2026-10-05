<?php

namespace App\Tracking;

final class TrackingSyncResult
{
    public array $providers = [];

    public array $applied = [];

    public array $skipped = [];

    public function applied(string $provider, TrackingUpdate $update, string $change): void
    {
        $this->applied[] = ['provider' => $provider, 'target' => $update->target(), 'change' => $change];
    }

    public function skipped(string $provider, TrackingUpdate $update, string $reason): void
    {
        $this->skipped[] = ['provider' => $provider, 'target' => $update->target(), 'reason' => $reason];
    }

    public function toArray(): array
    {
        return ['providers' => $this->providers, 'applied' => $this->applied, 'skipped' => $this->skipped];
    }
}
