<?php

namespace Tests\Unit;

use App\Enums\CargoStatus;
use App\Enums\ProductCategory;
use App\Enums\ShippingMethod;
use App\Services\Import\ImportReport;
use App\Services\Import\RowNormalizer;
use Tests\TestCase;

class RowNormalizerTest extends TestCase
{
    private RowNormalizer $normalizer;

    private ImportReport $report;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new RowNormalizer;
        $this->report = new ImportReport;
    }

    private function row(array $overrides = []): array
    {
        return [
            'customer_name' => 'Fisk AS Mid',
            'proforma_invoice_no' => 862600369.0,
            'exporter_reference' => 442500481,
            'product_code' => 45407002520,
            'net_type' => 'Net cage',
            'product_description' => 'NP-157/152Cx1.2+15+15/SACEFob/1.8-30.5HM',
            'quantity' => 1,
            'unit' => 'PC',
            'net_weight_kg' => 3106,
            'gross_weight_kg' => 3206,
            'package_no' => 'WENA190219',
            'tag_number' => 2604101,
            'shipping_method' => 'Sea',
            'destination_port' => 'ORKANGER',
            'container_no' => 'HLBU8073625',
            'container_type' => '45UT',
            'shipping_line' => 'TRANSSEA AS',
            'etd' => 46200.0,
            'eta_planned' => 46239.0,
            'eta_actual' => 46256.0,
            'status' => 'In Transit',
            ...$overrides,
        ];
    }

    public function test_a_clean_row_is_normalised(): void
    {
        $row = $this->normalizer->normalize(121, $this->row(), $this->report);

        $this->assertNotNull($row);
        $this->assertSame('862600369', $row->proformaInvoiceNo);
        $this->assertSame('442500481', $row->exporterReference);
        $this->assertSame('GNP-2604101', $row->tagNumber);
        $this->assertSame('Net Cage', $row->netType);
        $this->assertSame(ProductCategory::NetPen, $row->productCategory);
        $this->assertSame(ShippingMethod::Sea, $row->shippingMethod);
        $this->assertSame(CargoStatus::InTransit, $row->status);
        $this->assertSame('2026-06-27', $row->etd->toDateString());
        $this->assertSame('2026-08-22', $row->etaActual->toDateString());
        $this->assertSame([], $this->report->issues());
    }

    public function test_status_spellings_map_to_the_flow(): void
    {
        foreach (['In Stock' => 'in_storage', 'in stock' => 'in_storage', 'In storage' => 'in_storage', 'Yet to Sail' => 'planned', 'Delivered' => 'delivered'] as $raw => $expected) {
            $row = $this->normalizer->normalize(1, $this->row(['status' => $raw]), $this->report);
            $this->assertSame($expected, $row->status->value, $raw);
            $this->assertSame($raw, $row->rawStatus);
        }
    }

    public function test_sold_is_imported_as_in_storage_with_a_warning(): void
    {
        $row = $this->normalizer->normalize(7, $this->row(['status' => 'Sold']), $this->report);

        $this->assertSame(CargoStatus::InStorage, $row->status);
        $this->assertSame('warning', $this->report->issues()[0]['severity']);
    }

    public function test_unknown_or_missing_status_rejects_the_row(): void
    {
        $this->assertNull($this->normalizer->normalize(5, $this->row(['status' => 'Lost at sea']), $this->report));
        $this->assertNull($this->normalizer->normalize(6, $this->row(['status' => null]), $this->report));
        $this->assertSame(2, $this->report->rejectedRows());
    }

    public function test_tag_placeholders_and_prefixes(): void
    {
        $this->assertNull($this->normalizer->normalize(1, $this->row(['tag_number' => ' - ']), $this->report)->tagNumber);
        $this->assertSame('GLS-2511017', $this->normalizer->normalize(2, $this->row(['tag_number' => 'GLS - 2511017']), $this->report)->tagNumber);
        $this->assertSame('GNP-2608002', $this->normalizer->normalize(3, $this->row(['tag_number' => 'GNP-2608002']), $this->report)->tagNumber);
    }

    public function test_invalid_optional_date_becomes_a_warning(): void
    {
        $row = $this->normalizer->normalize(59, $this->row(['customer_delivery_date' => '?']), $this->report);

        $this->assertNotNull($row);
        $this->assertNull($row->customerDeliveryDate);
        $this->assertSame('Cust Delivery date', $this->report->issues()[0]['column']);
    }

    public function test_text_dates_are_understood(): void
    {
        $row = $this->normalizer->normalize(1, $this->row(['etd' => '27.06.2026', 'eta_planned' => '2026-08-05', 'eta_actual' => null]), $this->report);

        $this->assertSame('2026-06-27', $row->etd->toDateString());
        $this->assertSame('2026-08-05', $row->etaPlanned->toDateString());
    }

    public function test_rows_that_cannot_be_imported_are_rejected_with_reasons(): void
    {
        $cases = [
            'invalid container' => ['container_no' => 'HLBU8073626'],
            'missing package' => ['package_no' => null],
            'etd after eta' => ['etd' => 46300.0],
            'awb on a sea shipment' => ['container_no' => '501-20079076'],
            'missing destination' => ['destination_port' => ''],
        ];

        foreach ($cases as $case => $overrides) {
            $report = new ImportReport;
            $this->assertNull($this->normalizer->normalize(10, $this->row($overrides), $report), $case);
            $this->assertSame('error', $report->issues()[0]['severity'], $case);
        }
    }

    public function test_air_rows_drop_the_air_container_type(): void
    {
        $row = $this->normalizer->normalize(109, $this->row([
            'shipping_method' => 'Air', 'container_no' => '501-20079076', 'container_type' => 'AIR', 'eta_actual' => null,
        ]), $this->report);

        $this->assertSame(ShippingMethod::Air, $row->shippingMethod);
        $this->assertNull($row->containerType);
    }
}
