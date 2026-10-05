<?php

namespace App\Services\Shipments;

use App\Enums\ChangeSource;
use App\Models\Import;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final readonly class ChangeContext
{
    public function __construct(
        public ChangeSource $source,
        public ?User $user = null,
        public ?string $reason = null,
        public string $group = '',
        public ?CarbonImmutable $occurredAt = null,
        public ?int $importId = null,
    ) {}

    public static function byUser(User $user, ?string $reason = null, ?CarbonImmutable $occurredAt = null): self
    {
        return new self(
            source: $user->is_service_account ? ChangeSource::Api : ChangeSource::Manual,
            user: $user,
            reason: self::clean($reason),
            group: (string) Str::uuid(),
            occurredAt: $occurredAt,
        );
    }

    public static function provider(string $provider, ?string $description = null, ?CarbonImmutable $occurredAt = null): self
    {
        return new self(
            source: ChangeSource::Provider,
            reason: trim("Update from {$provider}".($description ? ": {$description}" : '')),
            group: (string) Str::uuid(),
            occurredAt: $occurredAt,
        );
    }

    public static function import(Import $import, ?User $user, string $reason, ?CarbonImmutable $occurredAt = null): self
    {
        return new self(
            source: ChangeSource::Import,
            user: $user,
            reason: $reason,
            group: (string) Str::uuid(),
            occurredAt: $occurredAt,
            importId: $import->id,
        );
    }

    public function asSystem(string $reason): self
    {
        return new self(ChangeSource::System, $this->user, $reason, $this->group, $this->occurredAt, $this->importId);
    }

    public function asImplied(): self
    {
        return new self($this->source, $this->user, trim($this->reason.' (implied step)'), $this->group, $this->occurredAt, $this->importId);
    }

    public function withOccurredAt(?CarbonImmutable $occurredAt): self
    {
        return new self($this->source, $this->user, $this->reason, $this->group, $occurredAt, $this->importId);
    }

    public function hasReason(): bool
    {
        return $this->reason !== null && $this->reason !== '';
    }

    private static function clean(?string $reason): ?string
    {
        $reason = trim((string) $reason);

        return $reason === '' ? null : $reason;
    }
}
