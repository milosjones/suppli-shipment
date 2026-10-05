<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

final class SheetReader
{
    private const HEADERS = [
        'customername' => 'customer_name',
        'customer' => 'customer_name',
        'customerpo' => 'customer_po',
        'invoiceno' => 'proforma_invoice_no',
        'proformainvoiceno' => 'proforma_invoice_no',
        'salesorderno' => 'exporter_reference',
        'exportersref' => 'exporter_reference',
        'exportersreference' => 'exporter_reference',
        'materialcode' => 'product_code',
        'productcode' => 'product_code',
        'nettype' => 'net_type',
        'materialdescription' => 'product_description',
        'productdescription' => 'product_description',
        'qty' => 'quantity',
        'quantity' => 'quantity',
        'unit' => 'unit',
        'netwkgs' => 'net_weight_kg',
        'netweightkg' => 'net_weight_kg',
        'grosswkgs' => 'gross_weight_kg',
        'grossweightkg' => 'gross_weight_kg',
        'batchno' => 'package_no',
        'packageno' => 'package_no',
        'tagnognp' => 'tag_number',
        'tagno' => 'tag_number',
        'tagnumber' => 'tag_number',
        'shippingmethod' => 'shipping_method',
        'destinationport' => 'destination_port',
        'containerawbno' => 'container_no',
        'containerno' => 'container_no',
        'sealno' => 'seal_no',
        'conttype' => 'container_type',
        'containertype' => 'container_type',
        'shippingline' => 'shipping_line',
        'incoterm' => 'incoterm',
        'etdmumbai' => 'etd',
        'etd' => 'etd',
        'plannedeta' => 'eta_planned',
        'eta' => 'eta_planned',
        'arrivaldateactualetaport' => 'eta_actual',
        'actualeta' => 'eta_actual',
        'customerapproveddeldate' => 'customer_approved_delivery_date',
        'currentstatus' => 'status',
        'status' => 'status',
        'storagedate' => 'storage_date',
        'storagefacility' => 'storage_facility',
        'custdeliverydate' => 'customer_delivery_date',
        'commentssite' => 'comments',
    ];

    public const REQUIRED = ['package_no', 'container_no', 'destination_port', 'etd', 'eta_planned', 'status'];

    private const REQUIRED_LABELS = [
        'package_no' => 'Batch No.',
        'container_no' => 'Container/ AWB No',
        'destination_port' => 'Destination Port',
        'etd' => 'ETD Mumbai',
        'eta_planned' => 'Planned ETA',
        'status' => 'Current Status',
    ];

    public function read(string $path): SheetData
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
        } catch (Throwable $e) {
            throw new ImportFileException('The file could not be read as a spreadsheet: '.$e->getMessage(), previous: $e);
        }

        [$sheet, $headerRow, $columns] = $this->locateTable($spreadsheet);

        $rows = [];
        $highestRow = $sheet->getHighestDataRow();

        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $values = [];

            foreach ($columns as $key => $column) {
                $cell = $sheet->getCell([$column, $row]);
                $values[$key] = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
            }

            $rows[$row] = $values;
        }

        $spreadsheet->disconnectWorksheets();

        return new SheetData($sheet->getTitle(), $headerRow, $columns, $rows);
    }

    private function locateTable(Spreadsheet $spreadsheet): array
    {
        $sheets = $spreadsheet->getAllSheets();

        usort($sheets, fn (Worksheet $a, Worksheet $b) => $this->isDeliveryPlan($b) <=> $this->isDeliveryPlan($a));

        $best = null;

        foreach ($sheets as $sheet) {
            [$headerRow, $columns] = $this->findHeader($sheet);
            $missing = array_diff(self::REQUIRED, array_keys($columns));

            if ($missing === []) {
                return [$sheet, $headerRow, $columns];
            }

            $best ??= $missing;
        }

        $labels = array_map(fn (string $key) => self::REQUIRED_LABELS[$key], $best ?? self::REQUIRED);

        throw new ImportFileException('Required columns are missing: '.implode(', ', $labels).'.');
    }

    private function findHeader(Worksheet $sheet): array
    {
        $best = [0, []];
        $lastColumn = min(200, Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));

        for ($row = 1; $row <= min(10, $sheet->getHighestDataRow()); $row++) {
            $columns = [];

            for ($column = 1; $column <= $lastColumn; $column++) {
                $key = self::HEADERS[$this->normalizeHeader($sheet->getCell([$column, $row])->getValue())] ?? null;

                if ($key !== null && ! isset($columns[$key])) {
                    $columns[$key] = $column;
                }
            }

            if (count($columns) > count($best[1])) {
                $best = [$row, $columns];
            }
        }

        return $best;
    }

    private function normalizeHeader(mixed $value): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim((string) $value)));
    }

    private function isDeliveryPlan(Worksheet $sheet): int
    {
        return (int) str_contains(mb_strtolower($sheet->getTitle()), 'delivery plan');
    }
}
