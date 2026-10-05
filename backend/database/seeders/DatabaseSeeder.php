<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use App\Services\Import\DeliveryPlanImporter;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(DeliveryPlanImporter $importer): void
    {
        if (Company::query()->exists()) {
            $this->command?->info('Database already has data; seeding skipped.');

            return;
        }

        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@suppli.test',
            'password' => 'password',
            'role' => UserRole::SuperAdmin,
        ]);

        $garware = Company::create(['name' => 'Garware Technical Fibres AS']);
        $admin = $this->companyUser($garware, 'Garware Admin', 'admin@garware.test', UserRole::CompanyAdmin, $superAdmin);
        $this->companyUser($garware, 'Garware Logistics', 'logistics@garware.test', UserRole::CompanyUser, $admin);
        User::create([
            'company_id' => $garware->id,
            'name' => 'Suppli integration',
            'role' => UserRole::CompanyUser,
            'is_service_account' => true,
            'created_by' => $admin->id,
        ]);

        $import = $importer->import(
            database_path('seeders/data/norway-delivery-plan.xlsx'),
            'Norway delivery plan Template.xlsx',
            $garware,
            $admin,
        );

        $this->command?->info(sprintf(
            'Imported delivery plan: %d packages, %d shipments, %d containers, %d rejected, %d warnings.',
            $import->imported_rows,
            $import->shipments_created,
            $import->containers_created,
            $import->rejected_rows,
            $import->warning_count,
        ));
    }

    private function companyUser(Company $company, string $name, string $email, UserRole $role, User $createdBy): User
    {
        return User::create([
            'company_id' => $company->id,
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'created_by' => $createdBy->id,
        ]);
    }
}
