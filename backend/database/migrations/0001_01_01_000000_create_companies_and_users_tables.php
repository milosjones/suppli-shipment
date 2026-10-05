<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX companies_name_unique ON companies (lower(name))');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('role', 20);
            $table->boolean('is_service_account')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'role']);
        });

        DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (lower(email))');
        $this->check('users', 'users_role_check', "role IN ('super_admin', 'company_admin', 'company_user')");
        $this->check('users', 'users_company_check', "(role = 'super_admin') = (company_id IS NULL)");
        $this->check('users', 'users_login_check', 'is_service_account OR (email IS NOT NULL AND password IS NOT NULL)');
        $this->check('users', 'users_service_account_role_check', "NOT is_service_account OR role = 'company_user'");
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('companies');
    }

    private function check(string $table, string $name, string $expression): void
    {
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
    }
};
