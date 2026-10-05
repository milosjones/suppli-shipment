<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateSuperAdmin extends Command
{
    protected $signature = 'users:create-super-admin
        {email : Login email}
        {--name=Super admin : Display name}
        {--password= : Password (prompted when omitted)}';

    protected $description = 'Create a super admin (manages companies and users; can work in any company)';

    public function handle(): int
    {
        $data = [
            'email' => mb_strtolower((string) $this->argument('email')),
            'name' => (string) $this->option('name'),
            'password' => $this->option('password') ?? $this->secret('Password'),
        ];

        $validator = Validator::make($data, [
            'email' => ['required', 'email', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::min(8)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::create([...$validator->validated(), 'role' => UserRole::SuperAdmin]);
        $this->info("Super admin {$data['email']} created.");

        return self::SUCCESS;
    }
}
