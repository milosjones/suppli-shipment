<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_logs_in_and_uses_the_token(): void
    {
        $user = User::factory()->admin()->create(['email' => 'ops@example.test']);

        $response = $this->postJson('/api/v1/auth/login', ['email' => 'OPS@example.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.email', 'ops@example.test')
            ->assertJsonPath('user.role', 'company_admin')
            ->assertJsonPath('user.permissions.manage_users', true)
            ->assertJsonPath('user.permissions.manage_companies', false);

        $this->withToken($response->json('token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.company.id', $user->company_id);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        User::factory()->create(['email' => 'ops@example.test']);

        $this->postJson('/api/v1/auth/login', ['email' => 'ops@example.test', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_integrations_cannot_log_in_with_a_password(): void
    {
        User::factory()->serviceAccount()->create(['email' => 'suppli@example.test', 'password' => 'password']);

        $this->postJson('/api/v1/auth/login', ['email' => 'suppli@example.test', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
    }

    public function test_deactivated_users_and_companies_cannot_log_in(): void
    {
        User::factory()->inactive()->create(['email' => 'gone@example.test']);
        User::factory()->for(Company::factory()->inactive())->create(['email' => 'closed@example.test']);

        foreach (['gone@example.test', 'closed@example.test'] as $email) {
            $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'password'])
                ->assertUnprocessable()
                ->assertJsonPath('errors.email.0', 'This account or its company has been deactivated.');
        }
    }

    public function test_requests_without_a_token_are_rejected(): void
    {
        $this->getJson('/api/v1/shipments')->assertUnauthorized();
    }

    public function test_logout_revokes_the_token(): void
    {
        $token = User::factory()->create()->createToken('web-ui', ['write'])->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_tokens_stop_working_when_the_user_is_deactivated(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('web-ui', ['write'])->plainTextToken;
        $user->update(['is_active' => false]);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_a_read_only_token_cannot_change_data(): void
    {
        $user = $this->signIn(abilities: []);
        $shipment = $this->createShipment($user->company);

        $this->getJson("/api/v1/shipments/{$shipment->id}")->assertOk();
        $this->postJson("/api/v1/shipments/{$shipment->id}/status", ['status' => 'in_transit'])->assertForbidden();
    }

    public function test_users_change_their_own_password(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('web-ui', ['write'])->plainTextToken;
        $other = $user->createToken('web-ui', ['write']);

        $this->withToken($current)->putJson('/api/v1/auth/password', [
            'current_password' => 'password',
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
        ])->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'a-new-password'])->assertOk();
    }

    public function test_super_admins_have_no_company_and_see_admin_permissions(): void
    {
        $this->signIn(User::factory()->superAdmin()->create());

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.role', UserRole::SuperAdmin->value)
            ->assertJsonPath('user.company', null)
            ->assertJsonPath('user.permissions.manage_companies', true)
            ->assertJsonPath('user.permissions.select_company', true);
    }
}
