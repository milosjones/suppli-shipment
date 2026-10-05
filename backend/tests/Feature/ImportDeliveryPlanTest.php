<?php

namespace Tests\Feature;

use App\Enums\CargoStatus;
use App\Enums\ImportStatus;
use App\Models\CargoItem;
use App\Models\Company;
use App\Models\Container;
use App\Models\Import;
use App\Models\Shipment;
use App\Models\ShipmentDateChange;
use App\Models\ShipmentStatusChange;
use App\Models\User;
use App\Services\Import\DeliveryPlanImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportDeliveryPlanTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = [
        'Customer Name', 'Invoice NO', 'Sales Order No', 'Material Code', 'Net type', 'Material Description', 'Qty',
        'Unit', 'Batch No.', 'TAGNO - GNP:', 'Shipping method', 'Destination Port', 'Container/ AWB No', 'Cont type',
        'Shipping Line', 'ETD Mumbai', 'Planned ETA', 'Arrival date/Actual ETA Port', 'Current Status', 'Cust Delivery date',
    ];

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->company = Company::factory()->create(['name' => 'Garware Technical Fibres AS']);
        $this->admin = User::factory()->for($this->company)->admin()->create();
    }

    private function deliveryPlan(): string
    {
        return database_path('seeders/data/norway-delivery-plan.xlsx');
    }

    private function import(string $path, bool $dryRun = false): Import
    {
        return app(DeliveryPlanImporter::class)->import($path, basename($path), $this->company, $this->admin, $dryRun);
    }

    public function test_the_customers_delivery_plan_is_imported(): void
    {
        $import = $this->import($this->deliveryPlan());

        $this->assertSame(ImportStatus::Completed, $import->status);
        $this->assertSame(
            [180, 178, 0, 0, 2, 7, 40, 66],
            [$import->total_rows, $import->imported_rows, $import->skipped_rows, $import->rejected_rows,
                $import->blank_rows, $import->warning_count, $import->shipments_created, $import->containers_created],
        );
        $this->assertSame(40, Shipment::query()->count());
        $this->assertSame(66, Container::query()->count());
        $this->assertSame(178, CargoItem::query()->count());

        $this->assertSame(
            ['delivered' => 42, 'in_storage' => 76, 'in_transit' => 60],
            CargoItem::query()->selectRaw('status, count(*) AS total')->groupBy('status')->orderBy('status')->pluck('total', 'status')->all(),
        );
        $this->assertSame('In stock', CargoItem::query()->where('package_no', 'WEEC018822')->value('raw_status'));

        $this->assertSame(0, CargoItem::query()->where('customer_name', 'Garware Technical Fibres AS')->count());

        $this->assertSame('GNP-2606018', CargoItem::query()->where('package_no', 'WENA192543')->value('tag_number'));
        $this->assertSame('GLS-2511017', CargoItem::query()->where('package_no', 'WECF005369')->value('tag_number'));
        $this->assertNull(CargoItem::query()->where('package_no', 'WENA150289')->value('tag_number'));

        $orkanger = Container::query()->where('container_no', 'HLBU8073625')->firstOrFail()->shipment;
        $this->assertSame(['2026-08-05', '2026-08-22', 17], [$orkanger->eta_original->toDateString(), $orkanger->eta->toDateString(), $orkanger->eta_delay_days]);
        $this->assertSame('import', ShipmentDateChange::query()->where('shipment_id', $orkanger->id)->where('field', 'eta')->sole()->source->value);

        $air = Container::query()->where('container_no', '501-20079076')->firstOrFail();
        $this->assertNull($air->container_type);
        $this->assertSame('air', $air->shipment->shipping_method->value);
    }

    public function test_history_is_reconstructed_from_the_sheet(): void
    {
        $this->import($this->deliveryPlan());

        $item = CargoItem::query()->where('package_no', 'WENA150289')->firstOrFail();
        $history = ShipmentStatusChange::query()->where('cargo_item_id', $item->id)->orderBy('occurred_at')->get();

        $this->assertSame(['in_transit', 'arrived', 'in_storage', 'delivered'], $history->map(fn ($row) => $row->to_status->value)->all());
        $this->assertSame(
            ['2024-10-08', '2024-11-21', '2024-11-22', '2026-07-13'],
            $history->map(fn ($row) => $row->occurred_at->setTimezone('Europe/Oslo')->toDateString())->all(),
        );
        $this->assertTrue($history->every(fn ($row) => $row->source->value === 'import' && $row->user_id === $this->admin->id));
    }

    public function test_rows_that_cannot_be_imported_are_reported(): void
    {
        $import = $this->import($this->deliveryPlan());
        $issues = collect($import->issues)->keyBy('row');

        $this->assertSame('Cust Delivery date', $issues[59]['column']);
        $this->assertStringContainsString('differs from 19.05.2026', $issues[101]['message']);
        $this->assertSame('Material Code', $issues[103]['column']);
    }

    public function test_importing_the_same_file_again_changes_nothing(): void
    {
        $this->import($this->deliveryPlan());
        $second = $this->import($this->deliveryPlan());

        $this->assertSame([0, 178, 0], [$second->imported_rows, $second->skipped_rows, $second->shipments_created]);
        $this->assertStringContainsString('already imported', collect($second->issues)->firstWhere('row', null)['message']);
        $this->assertSame(178, CargoItem::query()->count());
    }

    public function test_a_dry_run_reports_without_saving(): void
    {
        $import = $this->import($this->deliveryPlan(), dryRun: true);

        $this->assertSame(178, $import->imported_rows);
        $this->assertTrue($import->dry_run);
        $this->assertSame(0, Shipment::query()->count());
        $this->assertSame(0, ShipmentStatusChange::query()->count());
        $this->assertTrue(Import::query()->whereKey($import->id)->exists());
    }

    public function test_dirty_rows_are_rejected_or_cleaned(): void
    {
        $good = $this->planRow();

        $import = $this->import($this->workbook([
            $good,
            $this->planRow(['Batch No.' => 'WENA2', 'Container/ AWB No' => 'HAMU4766089']),
            $this->planRow(['Batch No.' => 'WENA3', 'ETD Mumbai' => '01.10.2026']),
            array_fill(0, count(self::HEADERS), null),
            $this->planRow(['Batch No.' => 'WENA5', 'Current Status' => 'Lost']),
            $this->planRow(['Batch No.' => null]),
            $this->planRow(['TAGNO - GNP:' => 2606019]),
            $this->planRow(['Batch No.' => 'WENA8', 'Cust Delivery date' => '?']),
            $this->planRow(['Batch No.' => 'WENA9', 'Current Status' => 'Sold']),
            $this->planRow(['Batch No.' => 'WENA10', 'Customer Name' => 'FINNMARK AS']),
        ]));

        $errors = collect($import->issues)->where('severity', 'error')->pluck('row')->all();

        $this->assertSame([3, 4, 6, 7, 8], $errors);
        $this->assertSame([4, 5, 1], [$import->imported_rows, $import->rejected_rows, $import->blank_rows]);
        $this->assertSame(['Finnmark AS'], CargoItem::query()->distinct()->pluck('customer_name')->all());
        $this->assertSame(CargoStatus::InStorage, CargoItem::query()->where('package_no', 'WENA9')->first()->status);
        $this->assertNull(CargoItem::query()->where('package_no', 'WENA8')->value('customer_delivery_date'));
    }

    public function test_a_file_without_the_required_columns_fails(): void
    {
        $import = $this->import($this->workbook([['Fisk AS', 1]], ['Customer Name', 'Qty']));

        $this->assertSame(ImportStatus::Failed, $import->status);
        $this->assertStringContainsString('Batch No.', $import->error_message);
    }

    public function test_the_upload_endpoint_stores_the_file_and_returns_the_report(): void
    {
        $this->signIn($this->admin);
        $upload = new UploadedFile($this->deliveryPlan(), 'Norway delivery plan Template.xlsx', null, null, true);

        $created = $this->post('/api/v1/imports', ['file' => $upload, 'dry_run' => '1'], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.dry_run', true)
            ->assertJsonPath('data.imported_rows', 178)
            ->assertJsonPath('data.issues.0.row', 59)
            ->assertJsonPath('data.user.id', $this->admin->id);

        $this->assertCount(1, Storage::disk('local')->allFiles("imports/{$this->company->id}"));

        $this->getJson('/api/v1/imports')->assertOk()->assertJsonMissingPath('data.0.issues');
        $this->getJson('/api/v1/imports/'.$created->json('data.id'))->assertOk()->assertJsonCount(7, 'data.issues');

        $broken = UploadedFile::fake()->createWithContent('plan.xlsx', 'not a spreadsheet');
        $this->post('/api/v1/imports', ['file' => $broken], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('data.status', 'failed');
    }

    public function test_a_checked_file_can_be_imported_without_uploading_it_again(): void
    {
        $this->signIn($this->admin);
        $upload = new UploadedFile($this->deliveryPlan(), 'Norway delivery plan Template.xlsx', null, null, true);
        $checked = $this->post('/api/v1/imports', ['file' => $upload, 'dry_run' => '1'], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame(0, Shipment::query()->count());

        $imported = $this->postJson("/api/v1/imports/{$checked}/commit")
            ->assertCreated()
            ->assertJsonPath('data.dry_run', false)
            ->assertJsonPath('data.file_name', 'Norway delivery plan Template.xlsx')
            ->assertJsonPath('data.imported_rows', 178)
            ->json('data.id');

        $this->assertSame(40, Shipment::query()->count());
        $this->postJson("/api/v1/imports/{$imported}/commit")->assertUnprocessable();
    }

    private function planRow(array $overrides = []): array
    {
        $row = array_combine(self::HEADERS, [
            'Finnmark AS', 862600578, 462600223, 25403000654, 'Net Cage', 'NP-164mCirx1.3+18+15/KNXWht/360p-16.5HM', 1,
            'PC', 'WENA1', 2606018, 'Sea', 'SALTEN', 'HAMU4766088', '45GP',
            'TRANSSEA AS', '08.08.2026', '29.09.2026', null, 'In Transit', null,
        ]);

        return array_values(array_merge($row, $overrides));
    }

    private function workbook(array $rows, ?array $headers = null): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Delivery Plan 2026');
        $sheet->fromArray($headers ?? self::HEADERS, null, 'A1');
        $sheet->fromArray($rows, null, 'A2', true);

        $path = tempnam(sys_get_temp_dir(), 'plan').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
