<?php

namespace App\Providers;

use App\Models\User;
use App\OpenApi\CompanyHeaderTransformer;
use App\Support\Tenancy\TenantContext;
use App\Tracking\Providers\FakeTrackingProvider;
use App\Webhooks\WebhookOutbox;
use Dedoc\Scramble\Scramble;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(WebhookOutbox::class);

        $this->app->singleton(
            FakeTrackingProvider::class,
            fn () => new FakeTrackingProvider((bool) config('tracking.fake.simulate')),
        );
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Gate::define('manage-companies', fn (User $user) => $user->isSuperAdmin() && ! $user->is_service_account);
        Gate::define('manage-users', fn (User $user) => $user->canManageUsers());

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)->by(
            mb_strtolower((string) $request->input('email')).'|'.$request->ip(),
        ));

        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $isValid) => $isValid
                && $token->tokenable instanceof User
                && $token->tokenable->isEnabled(),
        );

        Scramble::configure()->withOperationTransformers(CompanyHeaderTransformer::class);
    }
}
