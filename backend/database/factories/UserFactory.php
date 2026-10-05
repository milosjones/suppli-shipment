<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::CompanyUser,
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => UserRole::CompanyAdmin]);
    }

    public function superAdmin(): static
    {
        return $this->state(['company_id' => null, 'role' => UserRole::SuperAdmin]);
    }

    public function serviceAccount(): static
    {
        return $this->state([
            'name' => 'Suppli integration',
            'email' => null,
            'password' => null,
            'role' => UserRole::CompanyUser,
            'is_service_account' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
