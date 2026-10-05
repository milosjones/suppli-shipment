<?php

namespace Tests\Feature\Webhooks;

use App\Models\Company;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->company = Company::factory()->create(['name' => 'Garware Technical Fibres AS']);
        $this->admin = User::factory()->for($this->company)->admin()->create();
    }

    public function test_admins_register_a_webhook_and_see_its_secret_once(): void
    {
        $this->signIn($this->admin);

        $webhook = $this->postJson('/api/v1/webhooks', [
            'url' => 'https://suppli.example.com/hooks/shipments',
            'events' => ['shipment.status_changed', 'shipment.dates_changed'],
            'description' => 'Suppli production',
        ])
            ->assertCreated()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.events', ['shipment.status_changed', 'shipment.dates_changed'])
            ->assertJsonPath('data.last_delivery', null)
            ->json('data');

        $this->assertStringStartsWith('whsec_', $webhook['secret']);
        $this->assertSame($webhook['secret'], WebhookEndpoint::query()->find($webhook['id'])->secret);
        $this->assertStringNotContainsString($webhook['secret'], DB::table('webhook_endpoints')->value('secret'));

        $this->getJson('/api/v1/webhooks')
            ->assertOk()
            ->assertJsonPath('data.0.description', 'Suppli production')
            ->assertJsonMissingPath('data.0.secret');

        $rotated = $this->postJson("/api/v1/webhooks/{$webhook['id']}/rotate-secret")->assertOk()->json('data.secret');
        $this->assertNotSame($webhook['secret'], $rotated);
        $this->assertSame($rotated, WebhookEndpoint::query()->find($webhook['id'])->secret);

        $this->patchJson("/api/v1/webhooks/{$webhook['id']}", ['events' => ['shipment.dates_changed'], 'description' => null])
            ->assertOk()
            ->assertJsonPath('data.events', ['shipment.dates_changed'])
            ->assertJsonPath('data.description', null)
            ->assertJsonMissingPath('data.secret');

        $this->deleteJson("/api/v1/webhooks/{$webhook['id']}")->assertNoContent();
        $this->assertSame(0, WebhookEndpoint::query()->count());
    }

    public function test_webhook_urls_must_be_public_https_addresses(): void
    {
        $this->signIn($this->admin);

        foreach ([
            'http://suppli.example.com/hooks',
            'https://localhost/hooks',
            'https://10.0.0.5/hooks',
            'https://[::1]/hooks',
            'https://api/hooks',
            'https://suppli.internal/hooks',
            'ftp://suppli.example.com/hooks',
        ] as $url) {
            $this->postJson('/api/v1/webhooks', ['url' => $url, 'events' => ['shipment.status_changed']])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('url');
        }

        $this->postJson('/api/v1/webhooks', ['url' => 'https://suppli.example.com/hooks', 'events' => ['shipment.created']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('events.0');
        $this->postJson('/api/v1/webhooks', ['url' => 'https://suppli.example.com/hooks', 'events' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('events');

        $this->postJson('/api/v1/webhooks', ['url' => 'https://suppli.example.com/hooks', 'events' => ['shipment.status_changed']])->assertCreated();
        $this->postJson('/api/v1/webhooks', ['url' => 'https://suppli.example.com/hooks', 'events' => ['shipment.dates_changed']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url' => 'already has a webhook for this URL']);

        config(['webhooks.allow_insecure_urls' => true]);
        $this->postJson('/api/v1/webhooks', ['url' => 'http://host.docker.internal:9000/hooks', 'events' => ['shipment.status_changed']])->assertCreated();
    }

    public function test_only_admins_of_the_company_manage_its_webhooks(): void
    {
        $other = Company::factory()->create();
        $foreign = $this->webhook($other);

        $this->signIn(User::factory()->for($this->company)->create());
        $this->getJson('/api/v1/webhooks')->assertForbidden();
        $this->postJson('/api/v1/webhooks', ['url' => 'https://suppli.example.com/hooks', 'events' => ['shipment.status_changed']])->assertForbidden();

        $this->signIn($this->admin);
        $this->getJson('/api/v1/webhooks')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson("/api/v1/webhooks/{$foreign->id}", ['is_active' => false])->assertNotFound();
        $this->getJson("/api/v1/webhooks/{$foreign->id}/deliveries")->assertNotFound();
        $this->postJson("/api/v1/webhooks/{$foreign->id}/test")->assertNotFound();

        $this->signIn(User::factory()->superAdmin()->create());
        $this->withHeader('X-Company-Id', (string) $other->id)
            ->getJson('/api/v1/webhooks')
            ->assertOk()
            ->assertJsonPath('data.0.id', $foreign->id);
    }

    public function test_a_test_event_shows_whether_the_endpoint_answers(): void
    {
        Http::fake(['suppli.example.com/*' => Http::response(['received' => true])]);
        $webhook = $this->webhook($this->company);
        $this->signIn($this->admin);

        $this->postJson("/api/v1/webhooks/{$webhook->id}/test")
            ->assertCreated()
            ->assertJsonPath('data.event_type', 'webhook.test')
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.response_status', 200)
            ->assertJsonPath('data.payload.company.name', 'Garware Technical Fibres AS');

        Http::assertSent(fn ($request) => $request->header('X-Webhook-Event') === ['webhook.test']);

        $this->getJson('/api/v1/webhooks')->assertJsonPath('data.0.last_delivery.status', 'succeeded');

        $this->patchJson("/api/v1/webhooks/{$webhook->id}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->postJson("/api/v1/webhooks/{$webhook->id}/test")->assertUnprocessable();
    }
}
