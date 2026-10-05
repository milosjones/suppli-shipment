<?php

namespace Tests\Feature\Shipments;

use App\Enums\CargoStatus;
use App\Models\CargoItem;
use App\Models\Company;
use App\Models\Shipment;
use App\Models\ShipmentDateChange;
use App\Models\ShipmentStatusChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StatusFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Shipment $shipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-01 10:00');
        $company = Company::factory()->create();
        $this->user = $this->signIn(User::factory()->for($company)->create(['name' => 'Ops User']));
        $this->shipment = $this->createShipment($company);
    }

    private function changeStatus(array $payload): TestResponse
    {
        return $this->postJson("/api/v1/shipments/{$this->shipment->id}/status", $payload);
    }

    public function test_a_shipment_level_change_moves_all_cargo_in_one_change_group(): void
    {
        $response = $this->changeStatus(['status' => 'in_transit', 'occurred_at' => '2026-06-27'])
            ->assertOk()
            ->assertJsonPath('changed', 3)
            ->assertJsonPath('shipment.status', 'in_transit')
            ->assertJsonPath('shipment.containers.0.status', 'in_transit');

        $rows = ShipmentStatusChange::query()->where('change_group', $response->json('change_group'))->get();

        $this->assertCount(3, $rows);
        $this->assertTrue($rows->every(fn ($row) => $row->from_status === CargoStatus::Planned
            && $row->to_status === CargoStatus::InTransit
            && $row->user_id === $this->user->id
            && $row->source->value === 'manual'));
    }

    public function test_steps_cannot_be_skipped(): void
    {
        $this->changeStatus(['status' => 'arrived'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertSame(0, CargoItem::query()->where('status', '!=', 'planned')->count());
    }

    public function test_departure_and_arrival_apply_to_whole_containers(): void
    {
        $item = $this->shipment->cargoQuery()->first();

        $this->changeStatus(['status' => 'in_transit', 'cargo_item_ids' => [$item->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_single_items_are_delivered_and_the_shipment_shows_the_least_advanced_status(): void
    {
        $this->advance($this->shipment, CargoStatus::InStorage, '2026-08-22');
        $item = $this->shipment->cargoQuery()->where('package_no', 'WENA190219')->first();

        $this->changeStatus(['status' => 'delivered', 'cargo_item_ids' => [$item->id], 'occurred_at' => '2026-08-25'])
            ->assertOk()
            ->assertJsonPath('changed', 1)
            ->assertJsonPath('shipment.status', 'in_storage')
            ->assertJsonPath('shipment.summary.status_counts.delivered', 1)
            ->assertJsonPath('shipment.summary.status_counts.in_storage', 2);

        $this->assertSame('2026-08-25', $item->fresh()->customer_delivery_date->toDateString());
    }

    public function test_arrival_sets_the_actual_eta_in_the_same_change_group(): void
    {
        $this->advance($this->shipment, CargoStatus::InTransit);

        $response = $this->changeStatus(['status' => 'arrived', 'occurred_at' => '2026-08-22'])->assertOk()
            ->assertJsonPath('shipment.eta', '2026-08-22')
            ->assertJsonPath('shipment.eta_original', '2026-08-05')
            ->assertJsonPath('shipment.eta_delay_days', 17);

        $dateChange = ShipmentDateChange::query()->where('change_group', $response->json('change_group'))->sole();
        $this->assertSame('eta', $dateChange->field->value);
        $this->assertSame('system', $dateChange->source->value);
        $this->assertSame(17, $dateChange->shift_days);
    }

    public function test_corrections_move_backwards_with_a_reason(): void
    {
        $this->advance($this->shipment, CargoStatus::Arrived, '2026-08-05');

        $this->changeStatus(['status' => 'in_transit', 'correction' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->changeStatus(['status' => 'in_transit', 'correction' => true, 'reason' => 'Arrival was registered for the wrong vessel'])
            ->assertOk()
            ->assertJsonPath('shipment.status', 'in_transit');

        $this->assertSame(3, ShipmentStatusChange::query()->where('is_correction', true)->count());
    }

    public function test_nothing_to_change_and_future_dates_are_rejected(): void
    {
        $this->changeStatus(['status' => 'planned'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->changeStatus(['status' => 'in_transit', 'occurred_at' => '2026-12-24'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('occurred_at');
    }

    public function test_containers_can_be_changed_separately(): void
    {
        $container = $this->shipment->containers()->where('container_no', 'HLBU8077765')->first();

        $this->changeStatus(['status' => 'in_transit', 'container_ids' => [$container->id], 'occurred_at' => '2026-06-27'])
            ->assertOk()
            ->assertJsonPath('changed', 1)
            ->assertJsonPath('shipment.status', 'planned');

        $history = $this->getJson("/api/v1/shipments/{$this->shipment->id}/history?type=status")->assertOk()->json('data');
        $departure = collect($history)->first(fn (array $group) => $group['status_changes'][0]['to'] === 'in_transit');

        $this->assertSame(['HLBU8077765'], $departure['status_changes'][0]['containers']);
        $this->assertSame(1, $departure['status_changes'][0]['items_count']);
        $this->assertSame('Ops User', $departure['user']['name']);
    }
}
