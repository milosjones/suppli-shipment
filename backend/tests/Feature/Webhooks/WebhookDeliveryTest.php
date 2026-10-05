<?php

namespace Tests\Feature\Webhooks;

use App\Enums\CargoStatus;
use App\Enums\WebhookDeliveryStatus;
use App\Jobs\DeliverWebhook;
use App\Models\Company;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Import\DeliveryPlanImporter;
use App\Webhooks\WebhookSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebhookDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Shipment $shipment;

    private WebhookEndpoint $endpoint;

    private string $suppli = 'up';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-08-24 10:00');
        Http::preventStrayRequests();
        Http::fake(fn () => match ($this->suppli) {
            'up' => Http::response(['received' => true]),
            'down' => Http::response('Service unavailable', 503),
            'unreachable' => Http::failedConnection('cURL error 28: Operation timed out after 10001 milliseconds'),
        });

        $this->company = Company::factory()->create(['name' => 'Garware Technical Fibres AS']);
        $this->signIn(User::factory()->for($this->company)->admin()->create(['name' => 'Ops User']));
        $this->shipment = $this->createShipment($this->company, ['external_reference' => 'SUPPLI-SHP-4711']);
        $this->endpoint = $this->webhook($this->company);
    }

    public function test_a_status_change_is_pushed_as_one_signed_event(): void
    {
        $group = $this->postJson("/api/v1/shipments/{$this->shipment->id}/status", ['status' => 'in_transit', 'occurred_at' => '2026-06-27'])
            ->assertOk()
            ->json('change_group');

        $delivery = WebhookDelivery::query()->sole();
        $this->assertSame(WebhookDeliveryStatus::Succeeded, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(200, $delivery->response_status);
        $this->assertSame($group, $delivery->change_group);

        $payload = $delivery->payload;
        $this->assertSame('shipment.status_changed', $payload['type']);
        $this->assertSame($delivery->event_id, $payload['id']);
        $this->assertSame(['id' => $this->company->id, 'name' => 'Garware Technical Fibres AS'], $payload['company']);
        $this->assertSame('SUPPLI-SHP-4711', $payload['shipment']['external_reference']);
        $this->assertSame('in_transit', $payload['shipment']['status']);
        $this->assertSame(['manual', 'Ops User'], [$payload['source'], $payload['changed_by']]);
        $this->assertCount(3, $payload['changes']);
        $this->assertSame(
            ['WENA190219', 'HLBU8073625', '862600369', 'planned', 'in_transit', false],
            array_values(array_intersect_key($payload['changes'][0], array_flip(['package_no', 'container_no', 'proforma_invoice_no', 'from', 'to', 'is_correction']))),
        );

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($delivery): bool {
            $timestamp = $request->header('X-Webhook-Timestamp')[0];

            return $request->url() === $this->endpoint->url
                && $request->header('X-Webhook-Id') === [$delivery->event_id]
                && $request->header('X-Webhook-Event') === ['shipment.status_changed']
                && $request->header('X-Webhook-Signature') === ['sha256='.hash_hmac('sha256', "{$timestamp}.{$request->body()}", 'whsec_test_secret')]
                && $request['changes'][2]['package_no'] === 'WENA191125';
        });

        $this->getJson("/api/v1/webhooks/{$this->endpoint->id}/deliveries")
            ->assertOk()
            ->assertJsonPath('data.0.shipment.reference', $this->shipment->reference)
            ->assertJsonPath('data.0.max_attempts', 8);
    }

    public function test_an_arrival_that_moves_the_eta_sends_a_status_and_a_dates_event(): void
    {
        $this->advance($this->shipment, CargoStatus::InTransit);
        WebhookDelivery::query()->delete();

        $group = $this->postJson("/api/v1/shipments/{$this->shipment->id}/status", ['status' => 'arrived', 'occurred_at' => '2026-08-21'])
            ->assertOk()
            ->json('change_group');

        [$status, $dates] = WebhookDelivery::query()->where('change_group', $group)->orderBy('id')->get()->all();
        $this->assertSame('shipment.status_changed', $status->payload['type']);
        $this->assertSame(['arrived', '2026-08-21'], [$status->payload['shipment']['status'], $status->payload['shipment']['eta']]);
        $this->assertSame('shipment.dates_changed', $dates->payload['type']);
        $this->assertSame('system', $dates->payload['source']);
        $this->assertSame('Arrival recorded on 21.08.2026', $dates->payload['reason']);
        $this->assertSame([[
            'field' => 'eta', 'package_no' => null, 'container_no' => null, 'proforma_invoice_no' => null, 'tag_number' => null,
            'customer_reference' => null, 'old' => '2026-08-05', 'new' => '2026-08-21', 'shift_days' => 16,
        ]], $dates->payload['changes']);
        $this->assertNotSame($status->event_id, $dates->event_id);
        $this->assertSame(WebhookDeliveryStatus::Succeeded, $dates->status);
    }

    public function test_webhooks_only_get_the_events_they_subscribe_to(): void
    {
        $this->endpoint->update(['events' => ['shipment.dates_changed']]);
        $container = $this->shipment->containers()->where('container_no', 'HLBU8073625')->sole();

        $this->postJson("/api/v1/shipments/{$this->shipment->id}/status", ['status' => 'in_transit', 'occurred_at' => '2026-06-27'])->assertOk();
        $this->assertSame(0, WebhookDelivery::query()->count());

        $this->postJson("/api/v1/shipments/{$this->shipment->id}/dates", [
            'customer_delivery_date' => '2026-09-01',
            'container_ids' => [$container->id],
            'reason' => 'Customer approved delivery date',
        ])->assertOk();

        $payload = WebhookDelivery::query()->sole()->payload;
        $this->assertSame('shipment.dates_changed', $payload['type']);
        $this->assertSame('Customer approved delivery date', $payload['reason']);
        $this->assertSame(['WENA190219', 'WENA190958'], array_column($payload['changes'], 'package_no'));
        $this->assertSame(['customer_delivery_date', null, '2026-09-01'], [$payload['changes'][0]['field'], $payload['changes'][0]['old'], $payload['changes'][0]['new']]);
    }

    public function test_nothing_is_sent_for_imports_paused_webhooks_or_rejected_changes(): void
    {
        Storage::fake('local');
        $this->webhook(Company::factory()->create());

        $this->postJson("/api/v1/shipments/{$this->shipment->id}/status", ['status' => 'arrived'])->assertUnprocessable();

        $this->endpoint->update(['is_active' => false]);
        $this->postJson("/api/v1/shipments/{$this->shipment->id}/status", ['status' => 'in_transit', 'occurred_at' => '2026-06-27'])->assertOk();

        $importing = Company::factory()->create();
        $this->webhook($importing);
        app(DeliveryPlanImporter::class)->import(database_path('seeders/data/norway-delivery-plan.xlsx'), 'plan.xlsx', $importing);

        $this->assertSame(0, WebhookDelivery::query()->count());
        Http::assertNothingSent();
    }

    public function test_changes_made_by_suppli_are_sent_back_marked_as_api(): void
    {
        $this->signIn(User::factory()->for($this->company)->serviceAccount()->create(['name' => 'Suppli integration']));

        $this->postJson("/api/v1/shipments/{$this->shipment->id}/dates", ['eta' => '2026-08-22', 'reason' => 'New ETA from Suppli'])->assertOk();

        $payload = WebhookDelivery::query()->sole()->payload;
        $this->assertSame(['api', 'Suppli integration', 'New ETA from Suppli'], [$payload['source'], $payload['changed_by'], $payload['reason']]);
    }

    public function test_failed_deliveries_are_retried_with_backoff_then_marked_failed_and_can_be_resent(): void
    {
        $this->suppli = 'down';
        $this->postJson("/api/v1/shipments/{$this->shipment->id}/dates", ['eta' => '2026-08-22', 'reason' => 'Vessel delayed'])->assertOk();

        $delivery = WebhookDelivery::query()->sole();
        $this->assertSame(WebhookDeliveryStatus::Pending, $delivery->status);
        $this->assertSame([1, 503, 'Service unavailable'], [$delivery->attempts, $delivery->response_status, $delivery->response_body]);
        $this->assertSame('2026-08-24 10:01:00', $delivery->next_attempt_at->format('Y-m-d H:i:s'));

        $sender = app(WebhookSender::class);
        $this->assertNull($sender->attempt($delivery->id));
        $this->assertSame(1, $delivery->fresh()->attempts);

        $this->travel(1)->minute();
        $this->assertSame(300, $sender->attempt($delivery->id));

        foreach ([300, 1800, 7200, 21600, 43200, 86400] as $wait) {
            $this->travel($wait)->seconds();
            $sender->attempt($delivery->id);
        }

        $failed = $delivery->fresh();
        $this->assertSame(WebhookDeliveryStatus::Failed, $failed->status);
        $this->assertSame(8, $failed->attempts);
        $this->assertNull($failed->next_attempt_at);
        Http::assertSentCount(8);

        $this->suppli = 'up';
        $this->postJson("/api/v1/webhook-deliveries/{$delivery->id}/retry")
            ->assertOk()
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.attempts', 1);

        Http::assertSent(fn (Request $request) => $request->header('X-Webhook-Id') === [$delivery->event_id]
            && $request->header('X-Webhook-Attempt') === ['1']
            && $request['id'] === $delivery->event_id);
    }

    public function test_connection_errors_are_recorded_and_retried(): void
    {
        $this->suppli = 'unreachable';

        $this->postJson("/api/v1/shipments/{$this->shipment->id}/dates", ['eta' => '2026-08-22', 'reason' => 'Vessel delayed'])->assertOk();

        $delivery = WebhookDelivery::query()->sole();
        $this->assertSame(WebhookDeliveryStatus::Pending, $delivery->status);
        $this->assertNull($delivery->response_status);
        $this->assertStringContainsString('timed out', $delivery->error);
    }

    public function test_a_paused_webhook_keeps_its_deliveries_until_it_is_resumed(): void
    {
        $this->suppli = 'down';
        $this->postJson("/api/v1/shipments/{$this->shipment->id}/dates", ['eta' => '2026-08-22', 'reason' => 'Vessel delayed'])->assertOk();
        $delivery = WebhookDelivery::query()->sole();

        $this->patchJson("/api/v1/webhooks/{$this->endpoint->id}", ['is_active' => false])->assertOk();
        $this->travel(5)->minutes();
        app(WebhookSender::class)->attempt($delivery->id);

        $this->assertSame([1, null], [$delivery->fresh()->attempts, $delivery->fresh()->next_attempt_at]);
        $this->postJson("/api/v1/webhook-deliveries/{$delivery->id}/retry")->assertUnprocessable();

        $this->suppli = 'up';
        $this->patchJson("/api/v1/webhooks/{$this->endpoint->id}", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.last_delivery.status', 'succeeded');

        $this->assertSame(2, $delivery->fresh()->attempts);
    }

    public function test_overdue_deliveries_are_queued_again(): void
    {
        Queue::fake();

        $this->postJson("/api/v1/shipments/{$this->shipment->id}/dates", ['eta' => '2026-08-22', 'reason' => 'Vessel delayed'])->assertOk();
        Queue::assertPushed(DeliverWebhook::class, 1);

        $this->artisan('webhooks:deliver-due')->assertSuccessful();
        Queue::assertPushed(DeliverWebhook::class, 1);

        $this->travel(10)->minutes();
        $this->artisan('webhooks:deliver-due')->expectsOutput('Queued 1 overdue webhook deliveries.')->assertSuccessful();
        Queue::assertPushed(DeliverWebhook::class, 2);
    }
}
