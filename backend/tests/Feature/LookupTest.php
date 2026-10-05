<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $company = Company::factory()->create();
        $this->signIn(User::factory()->for($company)->create());

        $this->createShipment($company, ['destination_port' => 'SALTEN'], [
            ['container_no' => 'HAMU4766088', 'items' => [
                ['package_no' => 'WENA192543', 'tag_number' => '2606018', 'product_code' => '25403000654', 'product_description' => 'NP-164mCirx1.3+18+15/KNXWht/360p-16.5HM', 'customer_name' => 'SJØMAT ØST AS', 'proforma_invoice_no' => '862600578'],
                ['package_no' => 'WENA192606', 'tag_number' => '2606018', 'product_code' => '25403000422', 'product_description' => 'DFColle-1.6mDia/N6132-16.5/V4PlatN/DblRi', 'customer_name' => 'SJØMAT ØST AS', 'proforma_invoice_no' => '862600578'],
            ]],
            ['container_no' => 'UETU6194142', 'items' => [
                ['package_no' => 'WENA192626', 'tag_number' => '2606020', 'product_description' => 'NP-164mCirx1.3+18+15/KNXWht/360p-16.5HM', 'customer_name' => 'Finnmark AS', 'proforma_invoice_no' => '862600635'],
            ]],
        ]);
    }

    public function test_by_container_number(): void
    {
        $this->getJson('/api/v1/lookup?q=hamu 4766088')
            ->assertOk()
            ->assertJsonPath('total_items', 2)
            ->assertJsonPath('results.0.shipment.destination_port', 'SALTEN')
            ->assertJsonPath('results.0.containers.0.container_no', 'HAMU4766088')
            ->assertJsonPath('results.0.containers.0.items.0.matched_on', ['container_no']);
    }

    public function test_by_proforma_invoice(): void
    {
        $this->getJson('/api/v1/lookup?q=862600635&type=invoice')->assertJsonPath('total_items', 1);
    }

    public function test_by_tag_in_any_spelling_finds_the_net_pen_and_its_dead_fish_collector(): void
    {
        foreach (['2606018', 'GNP-2606018', 'gnp 2606018'] as $query) {
            $this->getJson('/api/v1/lookup?type=tag&q='.urlencode($query))
                ->assertOk()
                ->assertJsonPath('total_items', 2, $query)
                ->assertJsonPath('results.0.containers.0.items.0.product_category_code', 'NP')
                ->assertJsonPath('results.0.containers.0.items.1.product_category_code', 'DF');
        }
    }

    public function test_by_product(): void
    {
        $this->getJson('/api/v1/lookup?q=DFColle&type=product')->assertJsonPath('total_items', 1);
        $this->getJson('/api/v1/lookup?q=25403000654')->assertJsonPath('total_items', 1);
    }

    public function test_by_customer_ignoring_case_including_norwegian_letters(): void
    {
        $this->getJson('/api/v1/lookup?q='.urlencode('sjømat øst').'&type=customer')
            ->assertOk()
            ->assertJsonPath('total_items', 2)
            ->assertJsonPath('results.0.containers.0.items.0.matched_on', ['customer_name']);
    }

    public function test_type_limits_the_fields_searched(): void
    {
        $this->getJson('/api/v1/lookup?q=HAMU4766088&type=customer')->assertJsonPath('total_items', 0);
        $this->getJson('/api/v1/lookup?q=x')->assertUnprocessable();
    }
}
