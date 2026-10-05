<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admins_create_companies_with_a_first_admin(): void
    {
        $this->signIn(User::factory()->superAdmin()->create());

        $this->postJson('/api/v1/admin/companies', [
            'name' => 'Havbruk Nord AS',
            'admin' => ['name' => 'Ingrid Admin', 'email' => 'Admin@Havbruk.test', 'password' => 'secret-password'],
        ])->assertCreated()->assertJsonPath('data.users_count', 1);

        $this->getJson('/api/v1/admin/companies')->assertOk()->assertJsonPath('data.0.name', 'Havbruk Nord AS');

        $this->postJson('/api/v1/auth/login', ['email' => 'admin@havbruk.test', 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonPath('user.role', 'company_admin')
            ->assertJsonPath('user.company.name', 'Havbruk Nord AS');
    }

    public function test_company_names_are_unique_ignoring_case(): void
    {
        Company::factory()->create(['name' => 'Havbruk Nord AS']);
        $this->signIn(User::factory()->superAdmin()->create());

        $this->postJson('/api/v1/admin/companies', ['name' => 'HAVBRUK NORD AS'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_company_admins_cannot_manage_companies(): void
    {
        $this->signIn(User::factory()->admin()->create());

        $this->getJson('/api/v1/admin/companies')->assertForbidden();
        $this->postJson('/api/v1/admin/companies', ['name' => 'Another AS'])->assertForbidden();
    }

    public function test_deactivating_a_company_signs_its_users_out(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create(['email' => 'ops@closed.test']);
        $user->createToken('web-ui', ['write']);
        $this->signIn(User::factory()->superAdmin()->create());

        $this->patchJson("/api/v1/admin/companies/{$company->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/v1/auth/login', ['email' => 'ops@closed.test', 'password' => 'password'])->assertUnprocessable();
    }
}
