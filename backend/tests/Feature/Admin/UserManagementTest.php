<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->for($this->company)->admin()->create();
    }

    public function test_company_admins_add_users_to_their_company(): void
    {
        $this->signIn($this->admin);
        User::factory()->for($this->company)->serviceAccount()->create();

        $this->postJson('/api/v1/users', [
            'name' => 'Ola Logistics',
            'email' => 'Ola@Example.test',
            'password' => 'secret-password',
            'role' => 'company_user',
        ])->assertCreated()->assertJsonPath('data.email', 'ola@example.test');

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertSame($this->admin->id, User::query()->where('email', 'ola@example.test')->value('created_by'));
    }

    public function test_super_admins_add_users_to_the_company_they_choose(): void
    {
        $other = Company::factory()->create();
        $this->signIn(User::factory()->superAdmin()->create());

        $this->withHeader('X-Company-Id', (string) $other->id)
            ->postJson('/api/v1/users', [
                'name' => 'Kari Nordmann',
                'email' => 'kari@example.test',
                'password' => 'secret-password',
                'role' => 'company_admin',
            ])
            ->assertCreated();

        $this->assertSame($other->id, User::query()->where('email', 'kari@example.test')->value('company_id'));
    }

    public function test_super_admins_see_the_users_of_every_company(): void
    {
        $other = Company::factory()->create(['name' => 'Nordic Demo AS']);
        $nordicAdmin = User::factory()->for($other)->admin()->create();
        User::factory()->for($other)->serviceAccount()->create();
        $superAdmin = User::factory()->superAdmin()->create();
        $this->signIn($superAdmin);

        $users = $this->getJson('/api/v1/admin/users')->assertOk();
        $this->assertEqualsCanonicalizing([$this->admin->id, $nordicAdmin->id, $superAdmin->id], $users->json('data.*.id'));

        $this->getJson("/api/v1/admin/users?company_id={$other->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.company.name', 'Nordic Demo AS');
    }

    public function test_super_admins_add_and_edit_users_in_any_company(): void
    {
        $other = Company::factory()->create();
        $serviceAccount = User::factory()->for($other)->serviceAccount()->create();
        $this->signIn(User::factory()->superAdmin()->create());

        $this->postJson('/api/v1/admin/users', [
            'name' => 'Kari Nordmann',
            'email' => 'kari@example.test',
            'password' => 'secret-password',
            'role' => 'company_admin',
        ])->assertUnprocessable()->assertJsonValidationErrors('company_id');

        $kari = $this->postJson('/api/v1/admin/users', [
            'company_id' => $other->id,
            'name' => 'Kari Nordmann',
            'email' => 'kari@example.test',
            'password' => 'secret-password',
            'role' => 'company_admin',
        ])->assertCreated()->assertJsonPath('data.company.id', $other->id)->json('data.id');

        $this->patchJson("/api/v1/admin/users/{$kari}", ['role' => 'company_user'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->patchJson("/api/v1/admin/users/{$this->admin->id}", ['name' => 'Garware Admin'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Garware Admin');

        $this->patchJson("/api/v1/admin/users/{$serviceAccount->id}", ['name' => 'Renamed'])->assertNotFound();
    }

    public function test_only_super_admins_manage_users_across_companies(): void
    {
        $this->signIn($this->admin);

        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->postJson('/api/v1/admin/users', [])->assertForbidden();
    }

    public function test_company_users_cannot_manage_users(): void
    {
        $this->signIn(User::factory()->for($this->company)->create());

        $this->getJson('/api/v1/users')->assertForbidden();
        $this->getJson('/api/v1/api-clients')->assertForbidden();
    }

    public function test_only_company_roles_can_be_assigned(): void
    {
        $this->signIn($this->admin);

        $this->postJson('/api/v1/users', ['name' => 'X', 'email' => 'x@example.test', 'password' => 'secret-password', 'role' => 'super_admin'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_admins_cannot_lock_themselves_or_the_company_out(): void
    {
        $this->signIn($this->admin);

        $this->patchJson("/api/v1/users/{$this->admin->id}", ['role' => 'company_user'])->assertUnprocessable();
        $this->patchJson("/api/v1/users/{$this->admin->id}", ['is_active' => false])->assertUnprocessable();

        $this->signIn(User::factory()->superAdmin()->create());
        $this->withHeader('X-Company-Id', (string) $this->company->id)
            ->patchJson("/api/v1/users/{$this->admin->id}", ['role' => 'company_user'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_deactivating_a_user_signs_them_out(): void
    {
        $user = User::factory()->for($this->company)->create();
        $user->createToken('web-ui', ['write']);
        $this->signIn($this->admin);

        $this->patchJson("/api/v1/users/{$user->id}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_users_of_other_companies_are_not_found(): void
    {
        $stranger = User::factory()->create();
        $this->signIn($this->admin);

        $this->patchJson("/api/v1/users/{$stranger->id}", ['name' => 'Hacked'])->assertNotFound();
    }

    public function test_api_clients_get_tokens_that_are_shown_once(): void
    {
        $this->signIn($this->admin);

        $client = $this->postJson('/api/v1/api-clients', ['name' => 'Suppli integration'])->assertCreated()->json('data');

        $token = $this->postJson("/api/v1/api-clients/{$client['id']}/tokens", [
            'name' => 'production',
            'abilities' => ['write', 'references:write'],
            'expires_in_days' => 365,
        ])->assertCreated()->assertJsonPath('abilities', ['write', 'references:write']);

        $this->getJson('/api/v1/api-clients')
            ->assertOk()
            ->assertJsonPath('data.0.tokens.0.name', 'production')
            ->assertJsonMissing(['token' => $token->json('token')]);

        $this->app['auth']->forgetGuards();
        $this->withToken($token->json('token'))
            ->patchJson('/api/v1/cargo-items/references', ['items' => [['package_no' => 'NONE']]])
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $this->signIn($this->admin);
        $this->deleteJson("/api/v1/api-clients/{$client['id']}/tokens/{$token->json('id')}")->assertNoContent();
        $this->patchJson("/api/v1/api-clients/{$client['id']}", ['is_active' => false])->assertOk();
        $this->assertFalse(User::query()->find($client['id'])->is_active);
    }

    public function test_disabled_api_clients_keep_their_tokens_until_they_are_enabled_again(): void
    {
        $this->signIn($this->admin);
        $client = $this->postJson('/api/v1/api-clients', ['name' => 'Suppli integration'])->json('data');
        $token = $this->postJson("/api/v1/api-clients/{$client['id']}/tokens", ['name' => 'production', 'abilities' => ['write']])->json('token');

        $this->patchJson("/api/v1/api-clients/{$client['id']}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonCount(1, 'data.tokens');
        $this->postJson("/api/v1/api-clients/{$client['id']}/tokens", ['name' => 'second'])->assertUnprocessable();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/shipments')->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->signIn($this->admin);
        $this->patchJson("/api/v1/api-clients/{$client['id']}", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/shipments')->assertOk();
    }
}
