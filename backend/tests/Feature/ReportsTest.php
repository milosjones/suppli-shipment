<?php

namespace Tests\Feature;

use App\Enums\CargoStatus;
use App\Models\Company;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Shipments\ChangeContext;
use App\Services\Shipments\DateChangeService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Shipment $atSea;

    private Shipment $inStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-01 10:00');
        $this->company = Company::factory()->create();
        $this->signIn(User::factory()->for($this->company)->create());

        $this->atSea = $this->createShipment($this->company, ['destination_port' => 'BERGEN', 'etd' => '2026-08-08', 'eta' => '2026-09-20'], [
            ['container_no' => 'HLXU5615674', 'items' => [
                ['package_no' => 'SEA-1', 'customer_name' => 'Fisk AS West', 'customer_delivery_date' => '2026-09-22', 'gross_weight_kg' => 2000],
                ['package_no' => 'SEA-2', 'customer_name' => 'Fisk AS West', 'customer_delivery_date' => '2026-10-10', 'gross_weight_kg' => 2000],
            ]],
        ]);
        $this->advance($this->atSea, CargoStatus::InTransit);
        $this->changeEta($this->atSea, '2026-09-25');

        $this->inStorage = $this->createShipment($this->company, ['destination_port' => 'ORKANGER', 'etd' => '2026-06-27', 'eta' => '2026-08-05'], [
            ['container_no' => 'HLBU8073625', 'items' => [
                ['package_no' => 'STORE-1', 'customer_name' => 'Fisk AS Mid', 'customer_delivery_date' => '2026-09-05', 'gross_weight_kg' => 3000],
                ['package_no' => 'STORE-2', 'customer_name' => 'Fisk ASA North', 'customer_delivery_date' => '2026-08-25', 'gross_weight_kg' => 3000],
            ]],
        ]);
        $this->advance($this->inStorage, CargoStatus::InStorage, '2026-08-05');
    }

    private function changeEta(Shipment $shipment, string $eta): void
    {
        app(TenantContext::class)->run($this->company, fn () => app(DateChangeService::class)->changeShipmentDates(
            $shipment, ['eta' => CarbonImmutable::parse($eta)], ChangeContext::byUser(User::factory()->for($this->company)->create(), 'Vessel delayed'),
        ));
    }

    public function test_upcoming_deliveries_for_a_date_range(): void
    {
        $this->getJson('/api/v1/reports/upcoming-deliveries?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertJsonCount(1, 'arrivals')
            ->assertJsonPath('arrivals.0.reference', $this->atSea->reference)
            ->assertJsonPath('arrivals.0.summary.items_count', 2)
            ->assertJsonCount(2, 'deliveries')
            ->assertJsonPath('deliveries.0.package_no', 'STORE-1')
            ->assertJsonPath('deliveries.0.shipment.reference', $this->inStorage->reference)
            ->assertJsonPath('deliveries.1.package_no', 'SEA-1')
            ->assertJsonCount(0, 'overdue_arrivals')
            ->assertJsonCount(1, 'overdue_deliveries')
            ->assertJsonPath('overdue_deliveries.0.package_no', 'STORE-2');
    }

    public function test_delivered_cargo_is_not_upcoming(): void
    {
        $item = $this->inStorage->cargoQuery()->where('package_no', 'STORE-1')->first();
        $this->postJson("/api/v1/shipments/{$this->inStorage->id}/status", ['status' => 'delivered', 'cargo_item_ids' => [$item->id]])->assertOk();

        $this->getJson('/api/v1/reports/upcoming-deliveries?from=2026-09-01&to=2026-09-30')
            ->assertJsonCount(1, 'deliveries')
            ->assertJsonPath('deliveries.0.package_no', 'SEA-1');
    }

    public function test_status_overview(): void
    {
        $response = $this->getJson('/api/v1/reports/status-overview')
            ->assertOk()
            ->assertJsonPath('totals.items', 4)
            ->assertJsonPath('totals.containers', 2)
            ->assertJsonPath('totals.shipments', 2)
            ->assertJsonPath('totals.gross_weight_kg', 10000)
            ->assertJsonPath('statuses.1.status', 'in_transit')
            ->assertJsonPath('statuses.1.items', 2)
            ->assertJsonPath('statuses.3.status', 'in_storage')
            ->assertJsonPath('statuses.3.containers', 1);

        $bergen = collect($response->json('by_destination'))->firstWhere('destination_port', 'BERGEN');
        $this->assertSame(2, $bergen['counts']['in_transit']);

        $this->getJson('/api/v1/reports/status-overview?customer=fisk as mid')->assertJsonPath('totals.items', 1);
    }

    public function test_delayed_shipments_overdue_and_at_risk_cargo(): void
    {
        $response = $this->getJson('/api/v1/reports/delayed?as_of=2026-09-01')->assertOk();

        $this->assertSame([$this->atSea->reference], collect($response->json('shipments'))->pluck('shipment.reference')->all());
        $this->assertSame(5, $response->json('shipments.0.eta_delay_days'));

        $this->assertSame(['STORE-2'], collect($response->json('items'))->pluck('item.package_no')->all());
        $this->assertSame(7, $response->json('items.0.overdue_days'));

        $this->assertSame(['SEA-1'], collect($response->json('at_risk'))->pluck('item.package_no')->all());
        $this->assertSame(3, $response->json('at_risk.0.days_short'));

        $this->getJson('/api/v1/reports/delayed?as_of=2026-09-01&min_days=10')
            ->assertJsonCount(0, 'shipments')
            ->assertJsonCount(0, 'items');
    }

    public function test_an_overdue_arrival_counts_as_delayed(): void
    {
        $this->getJson('/api/v1/reports/delayed?as_of=2026-10-01&min_days=3')
            ->assertJsonPath('shipments.0.shipment.reference', $this->atSea->reference)
            ->assertJsonPath('shipments.0.overdue_days', 6)
            ->assertJsonPath('shipments.0.max_delay_days', 6);
    }
}
