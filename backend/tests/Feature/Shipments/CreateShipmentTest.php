<?php

namespace Tests\Feature\Shipments;

use App\Enums\CargoStatus;
use App\Models\ShipmentStatusChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateShipmentTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return [
            'shipping_method' => 'sea',
            'shipping_line' => 'TRANSSEA AS',
            'destination_port' => 'Salten',
            'incoterm' => 'CIF',
            'etd' => '2026-08-08',
            'eta' => '2026-09-29',
            'external_reference' => 'SUPPLI-4711',
            'containers' => [
                ['container_no' => 'HAMU4766088', 'seal_no' => 'SL-1001', 'container_type' => '45gp', 'items' => [
                    ['package_no' => 'WENA192543', 'tag_number' => '2606018', 'product_description' => 'NP-164mCirx1.3+18+15/KNXWht/360p-16.5HM', 'net_type' => 'Net Cage', 'customer_name' => 'Finnmark AS', 'proforma_invoice_no' => '862600578', 'exporter_reference' => '462600223'],
                    ['package_no' => 'WENA192606', 'tag_number' => '2606018', 'product_description' => 'DFColle-1.6mDia/N6132-16.5/V4PlatN/DblRi', 'net_type' => 'DFC', 'customer_name' => 'Finnmark AS', 'proforma_invoice_no' => '862600578'],
                ]],
            ],
            ...$overrides,
        ];
    }

    public function test_a_shipment_is_registered_with_containers_and_cargo(): void
    {
        $user = $this->signIn();

        $response = $this->postJson('/api/v1/shipments', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'planned')
            ->assertJsonPath('data.destination_port', 'SALTEN')
            ->assertJsonPath('data.origin_port', 'NHAVA SHEVA')
            ->assertJsonPath('data.eta_original', '2026-09-29')
            ->assertJsonPath('data.summary.items_count', 2)
            ->assertJsonPath('data.containers.0.container_type', '45GP')
            ->assertJsonPath('data.containers.0.items.0.tag_number', 'GNP-2606018')
            ->assertJsonPath('data.containers.0.items.0.product_category', 'net_pen')
            ->assertJsonPath('data.containers.0.items.1.product_category_code', 'DF');

        $this->assertMatchesRegularExpression('/^SHP-\d{5}$/', $response->json('data.reference'));
        $this->assertSame(2, ShipmentStatusChange::query()->where('to_status', 'planned')->where('user_id', $user->id)->count());
    }

    public function test_container_numbers_need_a_valid_check_digit(): void
    {
        $this->signIn();
        $payload = $this->payload();
        $payload['containers'][0]['container_no'] = 'HAMU4766089';

        $this->postJson('/api/v1/shipments', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('containers.0.container_no');
    }

    public function test_air_shipments_use_air_waybills(): void
    {
        $this->signIn();
        $payload = $this->payload(['shipping_method' => 'air', 'shipping_line' => 'Aqua Airlines', 'destination_port' => 'OSLO']);
        $payload['containers'][0]['container_no'] = '501-20079076';

        $this->postJson('/api/v1/shipments', $payload)->assertCreated();

        $payload['containers'][0]['container_no'] = 'HAMU4766088';
        $payload['external_reference'] = 'SUPPLI-4712';
        $payload['containers'][0]['items'] = [['package_no' => 'AIR-2']];

        $this->postJson('/api/v1/shipments', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('containers.0.container_no');
    }

    public function test_eta_cannot_be_before_etd(): void
    {
        $this->signIn();

        $this->postJson('/api/v1/shipments', $this->payload(['etd' => '2026-10-01', 'eta' => '2026-09-29']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('eta');
    }

    public function test_duplicates_are_rejected(): void
    {
        $user = $this->signIn();
        $this->createShipment($user->company);

        $payload = $this->payload();
        $payload['containers'][0]['items'][0]['package_no'] = 'WENA190219';
        $payload['containers'][] = ['container_no' => 'HAMU4766088', 'items' => [['package_no' => 'NEW-1']]];

        $this->postJson('/api/v1/shipments', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['containers.0.items.0.package_no', 'containers.1.container_no']);
    }

    public function test_the_same_external_reference_returns_conflict(): void
    {
        $this->signIn();
        $first = $this->postJson('/api/v1/shipments', $this->payload())->assertCreated();

        $payload = $this->payload();
        $payload['containers'][0]['items'] = [['package_no' => 'OTHER-1']];

        $this->postJson('/api/v1/shipments', $payload)
            ->assertConflict()
            ->assertJsonPath('shipment_id', $first->json('data.id'));
    }

    public function test_a_shipment_needs_containers_with_cargo(): void
    {
        $this->signIn();

        $this->postJson('/api/v1/shipments', $this->payload(['containers' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('containers');

        $this->postJson('/api/v1/shipments', $this->payload(['containers' => [['container_no' => 'HAMU4766088', 'items' => []]]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('containers.0.items');
    }

    public function test_cargo_can_be_added_only_before_sailing(): void
    {
        $user = $this->signIn();
        $shipment = $this->createShipment($user->company);

        $this->postJson("/api/v1/shipments/{$shipment->id}/containers", [
            'container_no' => 'HLBU8099260',
            'items' => [['package_no' => 'WENA191110']],
        ])->assertCreated()->assertJsonPath('data.container_no', 'HLBU8099260');

        $this->advance($shipment, CargoStatus::InTransit);

        $this->postJson("/api/v1/shipments/{$shipment->id}/containers", [
            'container_no' => 'HLBU8212802',
            'items' => [['package_no' => 'WEEC020080']],
        ])->assertUnprocessable()->assertJsonValidationErrors('shipment');

        $this->deleteJson("/api/v1/shipments/{$shipment->id}")->assertUnprocessable();
    }

    public function test_descriptive_fields_and_seal_numbers_can_be_updated(): void
    {
        $user = $this->signIn();
        $shipment = $this->createShipment($user->company);
        $container = $shipment->containers()->first();

        $this->patchJson("/api/v1/shipments/{$shipment->id}", ['shipping_line' => 'SKARSK', 'notes' => 'Book truck'])
            ->assertOk()
            ->assertJsonPath('data.shipping_line', 'SKARSK');

        $this->patchJson("/api/v1/containers/{$container->id}", ['seal_no' => 'SL-778899'])
            ->assertOk()
            ->assertJsonPath('data.seal_no', 'SL-778899');

        $this->patchJson("/api/v1/shipments/{$shipment->id}", ['shipping_method' => 'air'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shipping_method');
    }
}
