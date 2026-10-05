<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['company_id', 'name', 'email', 'password', 'role', 'is_service_account', 'is_active', 'created_by'])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_service_account' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isCompanyAdmin(): bool
    {
        return $this->role === UserRole::CompanyAdmin;
    }

    public function canManageUsers(): bool
    {
        return ! $this->is_service_account && ($this->isSuperAdmin() || $this->isCompanyAdmin());
    }

    public function isEnabled(): bool
    {
        return $this->is_active && ($this->company_id === null || (bool) $this->company?->is_active);
    }
}
