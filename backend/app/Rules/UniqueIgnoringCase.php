<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class UniqueIgnoringCase implements ValidationRule
{
    public function __construct(
        private readonly string $table,
        private readonly string $column,
        private readonly ?int $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = DB::table($this->table)
            ->whereRaw("lower({$this->column}) = ?", [mb_strtolower(trim((string) $value))])
            ->when($this->ignoreId !== null, fn ($query) => $query->where('id', '!=', $this->ignoreId))
            ->exists();

        if ($exists) {
            $fail('The :attribute has already been taken.');
        }
    }
}
