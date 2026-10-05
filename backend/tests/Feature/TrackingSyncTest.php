<?php

namespace Tests\Feature;

use App\Enums\CargoStatus;
use App\Models\Company;
use App\Models\Shipment;
use App\Models\ShipmentDateChange;
use App\Models\ShipmentStatusChange;
use App\Models\User;
use App\Tracking\Providers\FakeTrackingProvider;
use App\Tracking\TrackingUpdate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TrackingSyncTest extends TestCase
{
    use RefreshDatabase;

    private Shipment $shipment;

    private FakeTrackingProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-01 10:00');
        $company = Company::factory()->create();
        $this->signIn(User::factory()->for($company)->create());
        $this->shipment = $this->createShipment($company);
        $this->provider = app(FakeTrackingProvider::class);
    }

    private function sync(): TestResponse
    {
        return $this->postJson("/api/v1/shipments/{$this->shipment->id}/tracking-sync")->assertOk();
    }

    public function test_provider_updates_go_through_the_status_flow(): void
    {
        $this->advance($this->shipment, CargoStatus::InTransit);
        $this->provider->push($this->shipment->reference, new TrackingUpdate(
            status: CargoStatus::Arrived,
            occurredAt: CarbonImmutable::parse('2026-08-21 06:30'),
            description: 'vessel berthed',
        ));

        $this->sync()
            ->assertJsonPath('providers', ['fake'])
            ->assertJsonCount(1, 'applied')
            ->assertJsonPath('shipment.status', 'arrived')
            ->assertJsonPath('shipment.eta', '2026-08-21');

        $change = ShipmentStatusChange::query()->where('to_status', 'arrived')->first();
        $this->assertSame('provider', $change->source->value);
        $this->assertSame('Update from fake: vessel berthed', $change->reason);
        $this->assertNull($change->user_id);
    }

    public function test_syncing_again_changes_nothing(): void
    {
        $this->advance($this->shipment, CargoStatus::InTransit);
        $this->provider->push($this->shipment->reference, new TrackingUpdate(status: CargoStatus::Arrived, occurredAt: CarbonImmutable::parse('2026-08-21 12:00')));

        $this->sync();
        $rows = ShipmentStatusChange::query()->count();

        $this->sync()->assertJsonCount(0, 'applied')->assertJsonCount(0, 'skipped');
        $this->assertSame($rows, ShipmentStatusChange::query()->count());
    }

    public function test_provider_updates_never_move_a_status_backwards(): void
    {
        $this->advance($this->shipment, CargoStatus::InStorage, '2026-08-06');
        $this->provider->push($this->shipment->reference, new TrackingUpdate(status: CargoStatus::InTransit));

        $this->sync()->assertJsonCount(0, 'applied')->assertJsonPath('shipment.status', 'in_storage');
    }

    public function test_unreported_steps_are_implied_without_changing_dates(): void
    {
        $this->provider->push($this->shipment->reference, new TrackingUpdate(status: CargoStatus::Arrived, occurredAt: CarbonImmutable::parse('2026-08-21 12:00')));

        $this->sync()
            ->assertJsonCount(2, 'applied')
            ->assertJsonPath('shipment.status', 'arrived')
            ->assertJsonPath('shipment.etd', '2026-06-27')
            ->assertJsonPath('shipment.eta', '2026-08-21');

        $this->assertStringEndsWith('(implied step)', ShipmentStatusChange::query()->where('to_status', 'in_transit')->first()->reason);
    }

    public function test_last_mile_updates_target_single_packages(): void
    {
        $this->advance($this->shipment, CargoStatus::InStorage, '2026-08-06');
        $this->provider->push($this->shipment->reference, new TrackingUpdate(
            status: CargoStatus::Delivered,
            occurredAt: CarbonImmutable::parse('2026-08-30 14:00'),
            packageNos: ['WENA190219'],
            description: 'delivered to the farm',
        ));

        $this->sync()
            ->assertJsonPath('shipment.summary.status_counts.delivered', 1)
            ->assertJsonPath('shipment.summary.status_counts.in_storage', 2);
    }

    public function test_the_command_syncs_every_company(): void
    {
        $this->advance($this->shipment, CargoStatus::InTransit);
        $this->app->instance(FakeTrackingProvider::class, new FakeTrackingProvider(simulate: true));

        $this->artisan('tracking:sync')->assertSuccessful();

        $this->assertSame(CargoStatus::Arrived, $this->shipment->fresh()->status);
    }

    public function test_updates_that_only_move_dates_are_recorded_as_provider_changes(): void
    {
        $this->advance($this->shipment, CargoStatus::InTransit);
        $this->provider->push(
            $this->shipment->reference,
            new TrackingUpdate(eta: CarbonImmutable::parse('2026-08-19'), description: 'new ETA from the carrier'),
            new TrackingUpdate(containerNo: 'HLBU8073625', customerDeliveryDate: CarbonImmutable::parse('2026-08-28')),
        );

        $this->sync()
            ->assertJsonCount(2, 'applied')
            ->assertJsonPath('shipment.status', 'in_transit')
            ->assertJsonPath('shipment.eta', '2026-08-19')
            ->assertJsonPath('shipment.eta_delay_days', 14);

        $eta = ShipmentDateChange::query()->where('field', 'eta')->sole();
        $this->assertSame('provider', $eta->source->value);
        $this->assertSame('Update from fake: new ETA from the carrier', $eta->reason);
        $this->assertSame(14, $eta->shift_days);
        $this->assertSame(2, ShipmentDateChange::query()->where('field', 'customer_delivery_date')->count());
        $this->assertSame(0, ShipmentStatusChange::query()->where('source', 'provider')->count());

        $this->sync()->assertJsonCount(0, 'applied')->assertJsonCount(0, 'skipped');
        $this->assertSame(3, ShipmentDateChange::query()->count());
    }
}
