<?php

namespace App\Services\Users;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UserAccounts
{
    public function create(Company $company, array $data, User $actor): User
    {
        return User::create([
            ...$data,
            'company_id' => $company->id,
            'created_by' => $actor->id,
        ]);
    }

    public function update(User $user, array $data, User $actor): User
    {
        $user->fill($data);

        if ($user->is($actor) && ($user->isDirty('role') || ($user->isDirty('is_active') && ! $user->is_active))) {
            throw ValidationException::withMessages(['role' => 'You cannot change your own role or deactivate yourself.']);
        }

        $losesAdmin = $user->getOriginal('role') === UserRole::CompanyAdmin
            && $user->getOriginal('is_active')
            && ($user->role !== UserRole::CompanyAdmin || ! $user->is_active);

        if ($losesAdmin && ! $this->hasAnotherActiveAdmin($user)) {
            throw ValidationException::withMessages(['role' => 'The company needs at least one active company admin.']);
        }

        $user->save();

        if (isset($data['password']) || ! $user->is_active) {
            $user->tokens()->delete();
        }

        return $user;
    }

    private function hasAnotherActiveAdmin(User $user): bool
    {
        return User::query()
            ->where('company_id', $user->company_id)
            ->where('role', UserRole::CompanyAdmin)
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->exists();
    }
}
