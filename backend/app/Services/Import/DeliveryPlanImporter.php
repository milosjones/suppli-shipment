<?php

namespace App\Services\Import;

use App\Enums\CargoStatus;
use App\Enums\ImportStatus;
use App\Models\CargoItem;
use App\Models\Company;
use App\Models\Container;
use App\Models\Import;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Shipments\ChangeContext;
use App\Services\Shipments\DateChangeService;
use App\Services\Shipments\ShipmentReference;
use App\Services\Shipments\ShipmentStatusRefresher;
use App\Support\Dates;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\File;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class DeliveryPlanImporter
{
    public function __construct(
        private readonly SheetReader $reader,
        private readonly RowNormalizer $normalizer,
        private readonly DateChangeService $dates,
        private readonly ShipmentStatusRefresher $refresher,
        private readonly TenantContext $tenant,
    ) {}

    public function import(string $path, string $fileName, Company $company, ?User $user = null, bool $dryRun = false): Import
    {
        return $this->tenant->run($company, function () use ($path, $fileName, $company, $user, $dryRun): Import {
            $import = Import::create([
                'company_id' => $company->id,
                'user_id' => $user?->id,
                'file_name' => $fileName,
                'file_path' => $this->store($path, $fileName, $company),
                'file_hash' => hash_file('sha256', $path),
                'status' => ImportStatus::Processing,
                'dry_run' => $dryRun,
                'started_at' => CarbonImmutable::now(),
            ]);

            $report = new ImportReport;

            try {
                $sheet = $this->reader->read($path);
                $this->warnIfAlreadyImported($import, $report);
                $this->persist($this->normalizeRows($sheet, $report), $import, $user, $report, $dryRun);
            } catch (ImportFileException $e) {
                return $this->finish($import, $report, ImportStatus::Failed, $e->getMessage());
            } catch (Throwable $e) {
                report($e);

                return $this->finish($import, $report, ImportStatus::Failed, 'The import stopped unexpectedly: '.$e->getMessage());
            }

            return $this->finish($import, $report, ImportStatus::Completed);
        });
    }

    public function importChecked(Import $checked, ?User $user = null): Import
    {
        $path = tempnam(sys_get_temp_dir(), 'plan');
        file_put_contents($path, Storage::disk(config('shipments.imports.disk'))->get($checked->file_path));

        try {
            return $this->import($path, $checked->file_name, $checked->company, $user);
        } finally {
            unlink($path);
        }
    }

    private function normalizeRows(SheetData $sheet, ImportReport $report): array
    {
        $rows = [];

        foreach ($sheet->rows as $number => $raw) {
            $report->totalRows++;

            if ($this->normalizer->isBlank($raw)) {
                $report->blankRows++;

                continue;
            }

            $row = $this->normalizer->normalize($number, $raw, $report);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        $this->canonicaliseCustomers($rows, $report);

        return $this->rejectDuplicatePackages($rows, $report);
    }

    private function canonicaliseCustomers(array $rows, ImportReport $report): void
    {
        $spellings = [];

        foreach ($rows as $row) {
            if ($row->customerName !== '') {
                $key = mb_strtolower($row->customerName);
                $spellings[$key][$row->customerName] = ($spellings[$key][$row->customerName] ?? 0) + 1;
            }
        }

        $canonical = array_map(function (array $variants): string {
            arsort($variants);

            return (string) array_key_first($variants);
        }, $spellings);

        foreach ($rows as $row) {
            $name = $canonical[mb_strtolower($row->customerName)] ?? $row->customerName;

            if ($name !== $row->customerName) {
                $report->normalised("customer '{$row->customerName}' → {$name}");
                $row->customerName = $name;
            }
        }
    }

    private function rejectDuplicatePackages(array $rows, ImportReport $report): array
    {
        $seen = [];

        return array_values(array_filter($rows, function (NormalizedRow $row) use (&$seen, $report): bool {
            $key = mb_strtoupper($row->packageNo);

            if (isset($seen[$key])) {
                $report->error($row->rowNumber, 'Batch No.', "Package {$row->packageNo} already appears in row {$seen[$key]}.", $row->packageNo);

                return false;
            }

            $seen[$key] = $row->rowNumber;

            return true;
        }));
    }

    private function persist(array $rows, Import $import, ?User $user, ImportReport $report, bool $dryRun): void
    {
        $voyages = collect($rows)->groupBy(fn (NormalizedRow $row) => $row->voyageKey());
        $this->warnAboutReusedContainers($voyages, $report);

        DB::beginTransaction();

        try {
            $history = new StatusHistoryBuffer($import, $user);
            $shipments = [];

            foreach ($voyages as $key => $voyageRows) {
                $shipment = $this->importVoyage((string) $key, $voyageRows, $import, $user, $report, $history);
                $shipments[$shipment->id] = $shipment;
            }

            $history->flush();

            foreach ($shipments as $shipment) {
                $this->refresher->refresh($shipment);
            }

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    private function importVoyage(string $key, Collection $rows, Import $import, ?User $user, ImportReport $report, StatusHistoryBuffer $history): Shipment
    {
        $first = $rows->first();
        $eta = $this->voyageEta($rows, $report);
        $shipment = Shipment::query()->where('source_key', $key)->first();

        if ($shipment === null) {
            [$id, $reference] = ShipmentReference::next();

            $shipment = new Shipment([
                'shipping_method' => $first->shippingMethod,
                'shipping_line' => $first->shippingLine,
                'origin_port' => config('shipments.default_origin_port'),
                'destination_port' => $first->destinationPort,
                'incoterm' => $first->incoterm,
                'etd' => $first->etd,
                'eta' => $first->etaPlanned,
                'status' => CargoStatus::Planned,
                'created_by' => $user?->id,
            ]);
            $shipment->id = $id;
            $shipment->reference = $reference;
            $shipment->source_key = $key;
            $shipment->save();
            $report->shipmentsCreated++;

            if (! Dates::sameDay($eta, $first->etaPlanned)) {
                $this->dates->changeShipmentDates($shipment, ['eta' => $eta], ChangeContext::import(
                    $import, $user, 'Imported: latest ETA ("Arrival date/Actual ETA Port") differs from the planned ETA',
                ));
            }
        }

        foreach ($rows->groupBy(fn (NormalizedRow $row) => $row->containerNo) as $containerNo => $containerRows) {
            $container = $shipment->containers()->where('container_no', $containerNo)->first();

            if ($container === null) {
                $container = $shipment->containers()->create([
                    'company_id' => $shipment->company_id,
                    'container_no' => $containerNo,
                    'seal_no' => $containerRows->pluck('sealNo')->filter()->first(),
                    'container_type' => $containerRows->pluck('containerType')->filter()->first(),
                ]);
                $report->containersCreated++;
            }

            foreach ($containerRows as $row) {
                $this->importPackage($row, $shipment, $container, $eta, $import, $user, $report, $history);
            }
        }

        return $shipment;
    }

    private function importPackage(NormalizedRow $row, Shipment $shipment, Container $container, CarbonImmutable $voyageEta, Import $import, ?User $user, ImportReport $report, StatusHistoryBuffer $history): void
    {
        $existing = CargoItem::query()->with('container')->where('package_no', $row->packageNo)->first();

        if ($existing !== null) {
            $report->skippedRows++;
            $this->warnIfDifferent($existing, $row, $report);

            return;
        }

        $baseline = $row->customerApprovedDeliveryDate ?? $row->customerDeliveryDate;
        $current = $row->customerDeliveryDate ?? $row->customerApprovedDeliveryDate;

        $item = $container->cargoItems()->create([
            'company_id' => $container->company_id,
            'package_no' => $row->packageNo,
            'proforma_invoice_no' => $row->proformaInvoiceNo,
            'exporter_reference' => $row->exporterReference,
            'tag_number' => $row->tagNumber,
            'product_code' => $row->productCode,
            'product_description' => $row->productDescription,
            'product_category' => $row->productCategory,
            'net_type' => $row->netType,
            'customer_name' => $row->customerName === '' ? null : $row->customerName,
            'customer_po' => $row->customerPo,
            'storage_facility' => $row->storageFacility,
            'quantity' => $row->quantity,
            'unit' => $row->unit,
            'net_weight_kg' => $row->netWeightKg,
            'gross_weight_kg' => $row->grossWeightKg,
            'status' => $row->status,
            'raw_status' => $row->rawStatus,
            'customer_delivery_date' => $baseline,
            'notes' => $row->comments,
        ]);

        if (! Dates::sameDay($baseline, $current)) {
            $this->dates->changeDeliveryDates($shipment, collect([$item]), $current, ChangeContext::import(
                $import, $user, 'Imported: delivery date differs from the customer-approved date',
            ));
        }

        $from = null;

        foreach ($this->statusTimeline($row, $voyageEta) as [$status, $day]) {
            $history->add($item, $shipment->id, $from, $status, $day);
            $from = $status;
        }

        $report->importedRows++;
    }

    private function statusTimeline(NormalizedRow $row, CarbonImmutable $voyageEta): array
    {
        $arrival = $row->etaActual ?? $voyageEta;
        $stored = $row->storageDate !== null ? $row->storageDate->max($arrival) : null;

        return match ($row->status) {
            CargoStatus::Planned => [[CargoStatus::Planned, Dates::today()]],
            CargoStatus::InTransit => [[CargoStatus::InTransit, $row->etd]],
            CargoStatus::Arrived => [[CargoStatus::InTransit, $row->etd], [CargoStatus::Arrived, $arrival]],
            CargoStatus::InStorage => [
                [CargoStatus::InTransit, $row->etd],
                [CargoStatus::Arrived, $arrival],
                [CargoStatus::InStorage, $stored ?? $arrival],
            ],
            CargoStatus::Delivered => array_values(array_filter([
                [CargoStatus::InTransit, $row->etd],
                [CargoStatus::Arrived, $arrival],
                $stored !== null ? [CargoStatus::InStorage, $stored] : null,
                [CargoStatus::Delivered, ($row->customerDeliveryDate ?? $stored ?? $arrival)->max($stored ?? $arrival)],
            ])),
        };
    }

    private function voyageEta(Collection $rows, ImportReport $report): CarbonImmutable
    {
        $actuals = $rows->map(fn (NormalizedRow $row) => $row->etaActual?->toDateString())->filter();

        if ($actuals->isEmpty()) {
            return $rows->first()->etaPlanned;
        }

        $chosen = (string) $actuals->countBy()->sortKeysDesc()->sortDesc()->keys()->first();

        foreach ($rows as $row) {
            if ($row->etaActual !== null && $row->etaActual->toDateString() !== $chosen) {
                $report->warning(
                    $row->rowNumber,
                    'Arrival date/Actual ETA Port',
                    "Actual ETA {$row->etaActual->format('d.m.Y')} differs from ".CarbonImmutable::parse($chosen)->format('d.m.Y').' used for this voyage (most rows of the voyage agree on it).',
                    $row->etaActual->toDateString(),
                );
            }
        }

        return CarbonImmutable::parse($chosen);
    }

    private function warnAboutReusedContainers(Collection $voyages, ImportReport $report): void
    {
        $firstSeen = [];

        foreach ($voyages as $key => $rows) {
            foreach ($rows->unique(fn (NormalizedRow $row) => $row->containerNo) as $row) {
                $seen = $firstSeen[$row->containerNo] ?? null;

                if ($seen !== null && $seen['voyage'] !== $key) {
                    $report->warning($row->rowNumber, 'Container/ AWB No', "Container {$row->containerNo} also appears on another voyage (row {$seen['row']}); it is treated as a separate container there.", $row->containerNo);
                }

                $firstSeen[$row->containerNo] ??= ['voyage' => $key, 'row' => $row->rowNumber];
            }
        }
    }

    private function warnIfDifferent(CargoItem $existing, NormalizedRow $row, ImportReport $report): void
    {
        $differences = array_keys(array_filter([
            'container' => $existing->container->container_no !== $row->containerNo,
            'status' => $existing->status !== $row->status,
            'customer delivery date' => ! Dates::sameDay($existing->customer_delivery_date, $row->customerDeliveryDate ?? $row->customerApprovedDeliveryDate),
        ]));

        if ($differences !== []) {
            $report->warning(
                $row->rowNumber,
                'Batch No.',
                "Package {$row->packageNo} was imported before; its stored ".implode(', ', $differences).' differ from this file and were not overwritten. Use the shipment page to change them.',
                $row->packageNo,
            );
        }
    }

    private function warnIfAlreadyImported(Import $import, ImportReport $report): void
    {
        $previous = Import::query()
            ->where('file_hash', $import->file_hash)
            ->where('status', ImportStatus::Completed)
            ->where('dry_run', false)
            ->whereKeyNot($import->id)
            ->latest('id')
            ->first();

        if ($previous !== null) {
            $when = $previous->created_at->setTimezone(config('shipments.timezone'))->format('d.m.Y H:i');
            $report->warning(null, null, "This file was already imported on {$when} (import #{$previous->id}); packages that exist are skipped.");
        }
    }

    private function store(string $path, string $fileName, Company $company): string
    {
        $extension = pathinfo($fileName, PATHINFO_EXTENSION) ?: 'xlsx';

        return Storage::disk(config('shipments.imports.disk'))->putFileAs(
            config('shipments.imports.directory').'/'.$company->id,
            new File($path),
            Str::uuid().'.'.$extension,
        );
    }

    private function finish(Import $import, ImportReport $report, ImportStatus $status, ?string $error = null): Import
    {
        $import->forceFill([
            'status' => $status,
            'total_rows' => $report->totalRows,
            'imported_rows' => $status === ImportStatus::Completed ? $report->importedRows : 0,
            'skipped_rows' => $report->skippedRows,
            'rejected_rows' => $report->rejectedRows(),
            'blank_rows' => $report->blankRows,
            'warning_count' => $report->warningCount(),
            'shipments_created' => $status === ImportStatus::Completed ? $report->shipmentsCreated : 0,
            'containers_created' => $status === ImportStatus::Completed ? $report->containersCreated : 0,
            'normalisations' => $report->normalisations(),
            'issues' => $report->issues(),
            'error_message' => $error,
            'finished_at' => CarbonImmutable::now(),
        ])->save();

        return $import;
    }
}
