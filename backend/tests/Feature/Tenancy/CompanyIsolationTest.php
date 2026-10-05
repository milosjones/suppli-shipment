<?php

namespace Tests\Feature\Tenancy;

use App\Models\Company;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $ours;

    private Company $theirs;

    private Shipment $ourShipment;

    private Shipment $theirShipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ours = Company::factory()->create(['name' => 'Ours AS']);
        $this->theirs = Company::factory()->create(['name' => 'Theirs AS']);
        $this->ourShipment = $this->createShipment($this->ours);
        $this->theirShipment = $this->createShipment($this->theirs, containers: [
            ['container_no' => 'MEDU4711735', 'items' => [['package_no' => 'THEIRS-1', 'customer_name' => 'Secret Customer AS', 'tag_number' => '2699999']]],
        ]);

        $this->signIn($this->user($this->ours));
    }

    public function test_lists_only_show_the_own_company(): void
    {
        $this->getJson('/api/v1/shipments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->ourShipment->id);

        $this->getJson('/api/v1/meta')
            ->assertOk()
            ->assertJsonMissing(['Secret Customer AS']);
    }

    public function test_another_companys_shipment_is_not_found(): void
    {
        $id = $this->theirShipment->id;

        $this->getJson("/api/v1/shipments/{$id}")->assertNotFound();
        $this->getJson("/api/v1/shipments/{$id}/history")->assertNotFound();
        $this->postJson("/api/v1/shipments/{$id}/status", ['status' => 'in_transit'])->assertNotFound();
        $this->postJson("/api/v1/shipments/{$id}/dates", ['eta' => '2026-08-20', 'reason' => 'Delay'])->assertNotFound();
        $this->patchJson("/api/v1/shipments/{$id}", ['notes' => 'x'])->assertNotFound();
        $this->deleteJson("/api/v1/shipments/{$id}")->assertNotFound();
        $this->patchJson('/api/v1/containers/'.$this->theirShipment->containers()->first()->id, ['seal_no' => 'X'])->assertNotFound();

        $this->assertSame('planned', $this->theirShipment->fresh()->status->value);
    }

    public function test_lookup_and_reports_do_not_leak_other_companies(): void
    {
        $this->getJson('/api/v1/lookup?q=MEDU4711735')->assertOk()->assertJsonPath('total_items', 0);
        $this->getJson('/api/v1/lookup?q=Secret')->assertOk()->assertJsonPath('total_items', 0);

        $this->getJson('/api/v1/reports/status-overview')
            ->assertOk()
            ->assertJsonPath('totals.items', 3)
            ->assertJsonPath('totals.shipments', 1);

        $this->getJson('/api/v1/reports/upcoming-deliveries?from=2026-08-01&to=2026-08-31&as_of=2026-06-01')
            ->assertOk()
            ->assertJsonCount(1, 'arrivals');
    }

    public function test_ids_from_another_company_in_a_payload_are_rejected(): void
    {
        $theirContainer = $this->theirShipment->containers()->first();

        $this->postJson("/api/v1/shipments/{$this->ourShipment->id}/status", [
            'status' => 'in_transit',
            'container_ids' => [$theirContainer->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('container_ids');
    }

    public function test_package_numbers_only_need_to_be_unique_within_a_company(): void
    {
        $this->postJson('/api/v1/shipments', [
            'shipping_method' => 'sea',
            'destination_port' => 'BERGEN',
            'containers' => [['container_no' => 'HAMU3021465', 'items' => [['package_no' => 'THEIRS-1']]]],
        ])->assertCreated();
    }

    public function test_super_admins_choose_the_company_with_a_header(): void
    {
        $this->signIn(User::factory()->superAdmin()->create());

        $this->getJson('/api/v1/shipments')->assertStatus(400);

        $this->withHeader('X-Company-Id', (string) $this->theirs->id)
            ->getJson('/api/v1/shipments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->theirShipment->id);

        $this->withHeader('X-Company-Id', '999999')->getJson('/api/v1/shipments')->assertNotFound();
    }

    public function test_company_users_cannot_switch_company_with_the_header(): void
    {
        $this->withHeader('X-Company-Id', (string) $this->theirs->id)
            ->getJson('/api/v1/shipments')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->ourShipment->id);
    }
}
