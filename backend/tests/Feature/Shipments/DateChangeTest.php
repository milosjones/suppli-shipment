<?php

namespace Tests\Feature\Shipments;

use App\Models\Company;
use App\Models\Shipment;
use App\Models\ShipmentDateChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DateChangeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-07-15 10:00');
        $company = Company::factory()->create();
        $this->user = $this->signIn(User::factory()->for($company)->create(['name' => 'Ops User']));
        $this->shipment = $this->createShipment($company);
    }

    public function test_an_eta_change_is_audited_and_shows_the_delay(): void
    {
        $this->postJson("/api/v1/shipments/{$this->shipment->id}/dates", [
            'eta' => '2026-08-22',
            'reason' => 'Vessel delayed by congestion in Colombo',
        ])
            ->assertOk()
            ->assertJsonPath('shipment.eta', '2026-08-22')
            ->assertJsonPath('shipment.eta_original', '2026-08-05')
            ->assertJsonPath('shipment.eta_delay_days', 17);

        $change = ShipmentDateChange::query()->sole();
        $this->assertSame('eta', $change->field->value);
        $this->assertSame('2026-08-05', $change->old_date->toDateString());
        $this->assertSame('2026-08-22', $change->new_date->toDateString());
        $this->assertSame(17, $change->shift_days);
        $this->assertSame($this->user->id, $change->user_id);
        $this->assertSame('Vessel delayed by congestion in Colombo', $change->reason);
        $this->assertNotNull($change->created_at);

        $this->postJson("/api/v1/shipments/{$this->shipment->id}/dates", ['eta' => '2026-08-12', 'reason' => 'Caught up'])
            ->assertJsonPath('shipment.eta_delay_days', 7);
    }

    public function test_a_reason_is_required(): void
    {
        $this->postJson("/api/v1/shipments/{$this->shipment->id}/dates", ['eta' => '2026-08-22'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }

    public function test_dates_are_checked_against_the_stored_counterpart(): void
    {
        $this->postJson("/api/v1/shipments/{$this->shipment->id}/dates", ['eta' => '2026-06-20', 'reason' => 'Typo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('eta');

        $this->postJson("/api/v1/shipments/{$this->shipment->id}/dates", ['eta' => '2026-08-05', 'reason' => 'Same date'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('dates');

        $this->assertSame(0, ShipmentDateChange::query()->count());
    }

    public function test_customer_delivery_dates_change_per_container(): void
    {
        $container = $this->shipment->containers()->where('container_no', 'HLBU8073625')->first();

        $this->postJson("/api/v1/shipments/{$this->shipment->id}/dates", [
            'customer_delivery_date' => '2026-08-25',
            'container_ids' => [$container->id],
            'reason' => 'Customer approved delivery date',
        ])->assertOk();

        $this->postJson("/api/v1/shipments/{$this->shipment->id}/dates", [
            'customer_delivery_date' => '2026-08-28',
            'container_ids' => [$container->id],
            'reason' => 'Customer asked to postpone',
        ])->assertOk()
            ->assertJsonPath('shipment.containers.0.items.0.customer_delivery_date', '2026-08-28')
            ->assertJsonPath('shipment.containers.0.items.0.customer_delivery_date_original', '2026-08-25')
            ->assertJsonPath('shipment.containers.0.items.0.delivery_delay_days', 3)
            ->assertJsonPath('shipment.containers.1.items.0.customer_delivery_date', null);

        $this->assertSame(4, ShipmentDateChange::query()->where('field', 'customer_delivery_date')->count());
    }

    public function test_history_shows_status_and_date_changes_together(): void
    {
        $this->travelTo('2026-07-15 11:00');
        $this->postJson("/api/v1/shipments/{$this->shipment->id}/dates", ['eta' => '2026-08-22', 'reason' => 'Vessel delayed'])->assertOk();

        $this->getJson("/api/v1/shipments/{$this->shipment->id}/history")
            ->assertOk()
            ->assertJsonPath('data.0.date_changes.0.field', 'eta')
            ->assertJsonPath('data.0.date_changes.0.shift_days', 17)
            ->assertJsonPath('data.0.date_changes.0.reason', 'Vessel delayed')
            ->assertJsonPath('data.0.user.name', 'Ops User')
            ->assertJsonPath('data.1.status_changes.0.to', 'planned');

        $this->getJson("/api/v1/shipments/{$this->shipment->id}/history?type=dates")
            ->assertJsonCount(1, 'data');
    }

    public function test_an_etd_change_is_audited_and_checked_against_the_eta(): void
    {
        $this->postJson("/api/v1/shipments/{$this->shipment->id}/dates", ['etd' => '2026-07-02', 'reason' => 'Vessel rolled over to the next sailing'])
            ->assertOk()
            ->assertJsonPath('shipment.etd', '2026-07-02')
            ->assertJsonPath('shipment.etd_original', '2026-06-27')
            ->assertJsonPath('shipment.etd_delay_days', 5);

        $change = ShipmentDateChange::query()->sole();
        $this->assertSame('etd', $change->field->value);
        $this->assertSame('2026-06-27', $change->old_date->toDateString());
        $this->assertSame('2026-07-02', $change->new_date->toDateString());
        $this->assertSame(5, $change->shift_days);

        $this->postJson("/api/v1/shipments/{$this->shipment->id}/dates", ['etd' => '2026-08-10', 'reason' => 'Typo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('etd');

        $this->postJson("/api/v1/shipments/{$this->shipment->id}/dates", ['etd' => '2026-07-06', 'eta' => '2026-08-12', 'reason' => 'New schedule from the line'])
            ->assertOk()
            ->assertJsonPath('shipment.etd_delay_days', 9)
            ->assertJsonPath('shipment.eta_delay_days', 7);

        $schedule = ShipmentDateChange::query()->where('reason', 'New schedule from the line')->get();
        $this->assertEqualsCanonicalizing(['etd', 'eta'], $schedule->map(fn (ShipmentDateChange $row) => $row->field->value)->all());
        $this->assertCount(1, $schedule->pluck('change_group')->unique());
    }

    public function test_delivery_dates_cannot_be_before_departure_or_stay_the_same(): void
    {
        $this->postJson("/api/v1/shipments/{$this->shipment->id}/dates", ['customer_delivery_date' => '2026-06-20', 'reason' => 'Typo'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer_delivery_date');

        $this->postJson("/api/v1/shipments/{$this->shipment->id}/dates", ['customer_delivery_date' => '2026-08-25', 'reason' => 'Customer approved delivery date'])
            ->assertOk();

        $this->postJson("/api/v1/shipments/{$this->shipment->id}/dates", ['customer_delivery_date' => '2026-08-25', 'reason' => 'Same date again'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer_delivery_date');

        $this->assertSame(3, ShipmentDateChange::query()->count());
    }
}
