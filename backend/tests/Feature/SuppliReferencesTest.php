<?php

namespace Tests\Feature;

use App\Models\CargoItem;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuppliReferencesTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->createShipment($this->company);
    }

    public function test_people_cannot_change_suppli_references(): void
    {
        $this->signIn(User::factory()->for($this->company)->admin()->create());

        $this->patchJson('/api/v1/cargo-items/references', ['items' => [['package_no' => 'WENA190219', 'customer_name' => 'Changed']]])
            ->assertForbidden();
    }

    public function test_the_suppli_integration_updates_references_by_package_number(): void
    {
        $this->travelTo(now()->addMinute());
        Sanctum::actingAs(User::factory()->for($this->company)->serviceAccount()->create(), ['write', 'references:write']);

        $this->patchJson('/api/v1/cargo-items/references', ['items' => [
            ['package_no' => 'WENA190219', 'customer_name' => 'Fisk AS Nord', 'storage_facility' => 'EK Warehouse', 'tag_number' => '2604199'],
            ['package_no' => 'UNKNOWN-1', 'customer_name' => 'Nobody'],
        ]])
            ->assertOk()
            ->assertJsonPath('updated', ['WENA190219'])
            ->assertJsonPath('not_found', ['UNKNOWN-1']);

        $item = CargoItem::query()->where('package_no', 'WENA190219')->first();
        $this->assertSame(['Fisk AS Nord', 'EK Warehouse', 'GNP-2604199'], [$item->customer_name, $item->storage_facility, $item->tag_number]);
        $this->assertTrue($item->container->shipment->updated_at->isSameMinute(now()));
    }

    public function test_changes_by_the_integration_are_attributed_to_it(): void
    {
        $suppli = User::factory()->for($this->company)->serviceAccount()->create();
        Sanctum::actingAs($suppli, ['write']);
        $shipment = $this->company->shipments()->first();

        $this->postJson("/api/v1/shipments/{$shipment->id}/dates", ['eta' => '2026-08-10', 'reason' => 'New ETA from the carrier'])->assertOk();

        $this->getJson("/api/v1/shipments/{$shipment->id}/history?type=dates")
            ->assertJsonPath('data.0.source', 'api')
            ->assertJsonPath('data.0.user.name', 'Suppli integration');
    }
}
