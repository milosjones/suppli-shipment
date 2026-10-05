<?php

namespace Tests\Feature\Shipments;

use App\Enums\CargoStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentListTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipments_are_filtered_and_summarised(): void
    {
        $this->travelTo('2026-09-01 10:00');
        $company = Company::factory()->create();
        $this->signIn(User::factory()->for($company)->create());

        $orkanger = $this->createShipment($company);
        $salten = $this->createShipment($company, ['destination_port' => 'SALTEN', 'etd' => '2026-08-08', 'eta' => '2026-09-29'], [
            ['container_no' => 'HAMU4766088', 'items' => [['package_no' => 'WENA192543', 'tag_number' => '2606018', 'customer_name' => 'Finnmark AS']]],
        ]);
        $this->advance($orkanger, CargoStatus::InTransit);

        $inTransit = $this->getJson('/api/v1/shipments?status=in_transit')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $orkanger->id)
            ->assertJsonPath('data.0.summary.items_count', 3)
            ->assertJsonPath('data.0.summary.containers_count', 2)
            ->assertJsonPath('data.0.summary.status_counts.in_transit', 3);
        $this->assertEqualsCanonicalizing(['Fisk AS Mid', 'Fisk ASA North'], $inTransit->json('data.0.summary.customers'));

        $this->getJson('/api/v1/shipments?destination_port=salten')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $salten->id);
        $this->getJson('/api/v1/shipments?customer=finnmark')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $salten->id);
        $this->getJson('/api/v1/shipments?q=HLBU8073625')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $orkanger->id);
        $this->getJson('/api/v1/shipments?q=GNP-2606018')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $salten->id);

        $this->getJson('/api/v1/shipments?overdue=1')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_overdue', true)
            ->assertJsonPath('data.0.overdue_days', 27);
        $this->getJson('/api/v1/shipments?delayed=1')->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/shipments?sort=eta&per_page=1')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $orkanger->id)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_shipments_are_sorted_by_column(): void
    {
        $company = Company::factory()->create();
        $this->signIn(User::factory()->for($company)->create());

        $orkanger = $this->createShipment($company);
        $salten = $this->createShipment($company, ['destination_port' => 'SALTEN'], [
            ['container_no' => 'HAMU4766088', 'items' => [['package_no' => 'WENA192543']]],
        ]);
        $this->advance($salten, CargoStatus::InTransit);

        $this->getJson('/api/v1/shipments?sort=status')->assertJsonPath('data.0.id', $orkanger->id);
        $this->getJson('/api/v1/shipments?sort=-status')->assertJsonPath('data.0.id', $salten->id);
        $this->getJson('/api/v1/shipments?sort=destination_port')->assertJsonPath('data.0.id', $orkanger->id);
        $this->getJson('/api/v1/shipments?sort=-destination_port')->assertJsonPath('data.0.id', $salten->id);
        $this->getJson('/api/v1/shipments?sort=customer_name')->assertUnprocessable()->assertJsonValidationErrors('sort');
    }

    public function test_integrations_can_poll_for_changes(): void
    {
        $company = Company::factory()->create();
        $this->signIn(User::factory()->for($company)->create());
        $this->travelTo('2026-09-01 10:00');
        $this->createShipment($company);
        $this->travelTo('2026-09-02 10:00');

        $this->getJson('/api/v1/shipments?updated_since=2026-09-01T12:00:00Z')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/shipments?updated_since=2026-09-01T09:00:00Z')->assertJsonCount(1, 'data');
    }

    public function test_shipments_are_filtered_by_method_line_and_eta_range(): void
    {
        $company = Company::factory()->create();
        $this->signIn(User::factory()->for($company)->create());

        $sea = $this->createShipment($company);
        $air = $this->createShipment($company, ['shipping_method' => 'air', 'shipping_line' => 'Aqua Airlines', 'destination_port' => 'OSLO', 'etd' => '2026-06-09', 'eta' => '2026-06-20'], [
            ['container_no' => '176-12345675', 'items' => [['package_no' => 'WEEC020080']]],
        ]);
        $later = $this->createShipment($company, ['shipping_line' => 'NOR LINES', 'etd' => '2026-08-08', 'eta' => '2026-09-29'], [
            ['container_no' => 'HAMU4766088', 'items' => [['package_no' => 'WENA192543']]],
        ]);

        $this->getJson('/api/v1/shipments?shipping_method=air')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $air->id);
        $this->getJson('/api/v1/shipments?shipping_line=NOR%20LINES')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $later->id);
        $this->getJson('/api/v1/shipments?eta_from=2026-08-01&sort=eta')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $sea->id)
            ->assertJsonPath('data.1.id', $later->id);
        $this->getJson('/api/v1/shipments?eta_to=2026-08-05&sort=eta')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $air->id)
            ->assertJsonPath('data.1.id', $sea->id);
        $this->getJson('/api/v1/shipments?eta_from=2026-08-01&eta_to=2026-08-31')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $sea->id);
        $this->getJson('/api/v1/shipments?shipping_method=ship')->assertUnprocessable()->assertJsonValidationErrors('shipping_method');
    }
}
