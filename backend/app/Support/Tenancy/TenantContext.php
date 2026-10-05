<?php

namespace App\Support\Tenancy;

use App\Models\Company;
use RuntimeException;

final class TenantContext
{
    private ?Company $company = null;

    public function set(?Company $company): void
    {
        $this->company = $company;
    }

    public function company(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->id;
    }

    public function has(): bool
    {
        return $this->company !== null;
    }

    public function require(): Company
    {
        return $this->company ?? throw new RuntimeException('No company selected for this operation.');
    }

    public function run(Company $company, callable $callback): mixed
    {
        $previous = $this->company;
        $this->company = $company;

        try {
            return $callback();
        } finally {
            $this->company = $previous;
        }
    }
}
