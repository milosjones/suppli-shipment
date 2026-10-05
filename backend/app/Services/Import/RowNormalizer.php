<?php

namespace App\Services\Import;

use App\Enums\CargoStatus;
use App\Enums\ProductCategory;
use App\Enums\ShippingMethod;
use App\Support\ContainerNumber;
use App\Support\TagNumber;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

final class RowNormalizer
{
    private const STATUSES = [
        'yet to sail' => CargoStatus::Planned,
        'planned' => CargoStatus::Planned,
        'in transit' => CargoStatus::InTransit,
        'arrived' => CargoStatus::Arrived,
        'in storage' => CargoStatus::InStorage,
        'in stock' => CargoStatus::InStorage,
        'sold' => CargoStatus::InStorage,
        'delivered' => CargoStatus::Delivered,
    ];

    private const NET_TYPES = [
        'net cage' => 'Net Cage',
        'net pen' => 'Net Cage',
        'np base' => 'NP Base',
        'dfc' => 'DFC',
        'repair panel' => 'Repair Panel',
        'lice shield' => 'Lice Shield',
        'bird net' => 'Bird Nets',
        'bird nets' => 'Bird Nets',
        'rope' => 'Rope',
        'twine' => 'Twine',
        'websling' => 'Websling',
        'shackles' => 'Shackles',
        'velcro straps' => 'Velcro Straps',
        'grading panel' => 'Grading Panel',
    ];

    private const DATE_FORMATS = ['d.m.Y', 'd.m.y', 'Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y H:i', 'Y-m-d H:i:s'];

    public function isBlank(array $raw): bool
    {
        foreach ($raw as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    public function normalize(int $row, array $raw, ImportReport $report): ?NormalizedRow
    {
        $packageNo = $this->text($raw['package_no'] ?? null);

        if ($packageNo === null) {
            $report->error($row, 'Batch No.', 'Package (batch) number is missing, so the package cannot be identified.');
        }

        $containerText = $this->text($raw['container_no'] ?? null);
        $method = $this->shippingMethod($row, $raw['shipping_method'] ?? null, $containerText, $report);
        $containerNo = $containerText === null ? null : ContainerNumber::normalize($containerText);

        if ($containerNo === null) {
            $report->error($row, 'Container/ AWB No', 'Container or AWB number is missing.');
        } elseif ($method !== null && ! ContainerNumber::isValidFor($method, $containerNo)) {
            $report->error($row, 'Container/ AWB No', "'{$containerText}' is not ".ContainerNumber::formatHint($method).'.', $containerText);
        }

        $destination = $this->upper($raw['destination_port'] ?? null);

        if ($destination === null) {
            $report->error($row, 'Destination Port', 'Destination port is missing.');
        }

        $etd = $this->date($row, 'ETD Mumbai', $raw['etd'] ?? null, $report, required: true);
        $etaActual = $this->date($row, 'Arrival date/Actual ETA Port', $raw['eta_actual'] ?? null, $report);
        $etaPlanned = $this->date($row, 'Planned ETA', $raw['eta_planned'] ?? null, $report, required: $etaActual === null);

        if ($etaPlanned === null && $etaActual !== null && ! $report->isRejected($row)) {
            $report->warning($row, 'Planned ETA', 'Planned ETA is missing; the actual ETA is used as the plan.');
            $etaPlanned = $etaActual;
        }

        if ($etd !== null && $etaPlanned !== null && $etd->gt($etaPlanned)) {
            $report->error($row, 'Planned ETA', "ETD ({$etd->format('d.m.Y')}) is after the planned ETA ({$etaPlanned->format('d.m.Y')}).");
        }

        if ($etd !== null && $etaActual !== null && $etaActual->lt($etd)) {
            $report->error($row, 'Arrival date/Actual ETA Port', "Arrival ({$etaActual->format('d.m.Y')}) is before ETD ({$etd->format('d.m.Y')}).");
        }

        [$status, $rawStatus] = $this->status($row, $raw['status'] ?? null, $report);

        if ($report->isRejected($row)) {
            return null;
        }

        $customer = $this->text($raw['customer_name'] ?? null);
        $invoice = $this->text($raw['proforma_invoice_no'] ?? null);
        $productCode = $this->text($raw['product_code'] ?? null);

        if ($customer === null) {
            $report->warning($row, 'Customer Name', 'Customer name is missing.');
        }

        if ($invoice === null) {
            $report->warning($row, 'Invoice NO', 'Proforma invoice number is missing.');
        }

        if ($productCode === null) {
            $report->warning($row, 'Material Code', 'Material (product) code is missing.');
        }

        $netType = $this->netType($raw['net_type'] ?? null, $report);
        $description = $this->text($raw['product_description'] ?? null);

        return new NormalizedRow(
            rowNumber: $row,
            packageNo: $packageNo,
            containerNo: $containerNo,
            shippingMethod: $method,
            destinationPort: $destination,
            etd: $etd,
            etaPlanned: $etaPlanned,
            status: $status,
            rawStatus: $rawStatus,
            customerName: $customer ?? '',
            customerPo: $this->text($raw['customer_po'] ?? null),
            proformaInvoiceNo: $invoice,
            exporterReference: $this->text($raw['exporter_reference'] ?? null),
            productCode: $productCode,
            netType: $netType,
            productDescription: $description,
            productCategory: ProductCategory::infer($netType, $description),
            quantity: $this->number($row, 'Qty', $raw['quantity'] ?? null, $report),
            unit: $this->upper($raw['unit'] ?? null),
            netWeightKg: $this->number($row, 'Net w kgs', $raw['net_weight_kg'] ?? null, $report),
            grossWeightKg: $this->number($row, 'Gross w kgs', $raw['gross_weight_kg'] ?? null, $report),
            tagNumber: $this->tag($raw['tag_number'] ?? null, $report),
            sealNo: $this->text($raw['seal_no'] ?? null),
            containerType: $this->containerType($raw['container_type'] ?? null, $report),
            shippingLine: $this->text($raw['shipping_line'] ?? null),
            incoterm: $this->upper($raw['incoterm'] ?? null),
            etaActual: $etaActual,
            customerApprovedDeliveryDate: $this->deliveryDate($row, 'Customer approved del date', $raw['customer_approved_delivery_date'] ?? null, $etd, $report),
            storageDate: $this->date($row, 'Storage date', $raw['storage_date'] ?? null, $report),
            storageFacility: $this->text($raw['storage_facility'] ?? null),
            customerDeliveryDate: $this->deliveryDate($row, 'Cust Delivery date', $raw['customer_delivery_date'] ?? null, $etd, $report),
            comments: $this->text($raw['comments'] ?? null),
        );
    }

    public function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return floor($value) == $value && abs($value) < 1e15 ? (string) (int) $value : (string) $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $text = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', (string) $value));

        return preg_match('/^[\s\-–—?]*$/u', $text) === 1 || mb_strtolower($text) === 'n/a' ? null : $text;
    }

    private function upper(mixed $value): ?string
    {
        $text = $this->text($value);

        return $text === null ? null : mb_strtoupper($text);
    }

    private function status(int $row, mixed $value, ImportReport $report): array
    {
        $text = $this->text($value);

        if ($text === null) {
            $report->error($row, 'Current Status', 'Status is missing.');

            return [null, null];
        }

        $key = mb_strtolower($text);
        $status = self::STATUSES[$key] ?? null;

        if ($status === null) {
            $report->error($row, 'Current Status', "Unknown status '{$text}'. Expected Yet to Sail, In Transit, Arrived, In Storage / In Stock or Delivered.", $text);

            return [null, $text];
        }

        if ($key === 'sold') {
            $report->warning($row, 'Current Status', "'Sold' is an order status kept in Suppli; imported as In storage.", $text);
        }

        $report->normalised("status '{$text}' → {$status->value}");

        return [$status, $text];
    }

    private function shippingMethod(int $row, mixed $value, ?string $containerNo, ImportReport $report): ?ShippingMethod
    {
        $text = $this->text($value);

        if ($text !== null) {
            $method = ShippingMethod::tryFrom(mb_strtolower($text)) ?? (mb_strtolower($text) === 'truck' ? ShippingMethod::Road : null);

            if ($method === null) {
                $report->error($row, 'Shipping method', "Unknown shipping method '{$text}'. Expected Sea, Air or Road.", $text);
            }

            return $method;
        }

        $detected = $containerNo === null ? null : ContainerNumber::detectMethod($containerNo);

        if ($detected !== null) {
            $report->warning($row, 'Shipping method', "Shipping method is missing; {$detected->label()} assumed from the number format.");

            return $detected;
        }

        $report->error($row, 'Shipping method', 'Shipping method is missing.');

        return null;
    }

    private function date(int $row, string $column, mixed $value, ImportReport $report, bool $required = false): ?CarbonImmutable
    {
        $date = $this->parseDate($value);

        if ($date === false) {
            $display = is_scalar($value) ? trim((string) $value) : '';
            $message = "'{$display}' is not a valid date";
            $required
                ? $report->error($row, $column, "{$message}.", $value)
                : $report->warning($row, $column, "{$message}; left empty.", $value);

            return null;
        }

        if ($date === null && $required) {
            $report->error($row, $column, "{$column} is missing.");
        }

        return $date;
    }

    private function deliveryDate(int $row, string $column, mixed $value, CarbonImmutable $etd, ImportReport $report): ?CarbonImmutable
    {
        $date = $this->date($row, $column, $value, $report);

        if ($date !== null && $date->lt($etd)) {
            $report->warning($row, $column, "Delivery date {$date->format('d.m.Y')} is before ETD ({$etd->format('d.m.Y')}); left empty.");

            return null;
        }

        return $date;
    }

    private function parseDate(mixed $value): CarbonImmutable|false|null
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        if (is_int($value) || is_float($value)) {
            return $value >= 36526 && $value <= 73050
                ? CarbonImmutable::instance(ExcelDate::excelToDateTimeObject($value))->startOfDay()
                : false;
        }

        if (is_string($value) && trim($value) === '?') {
            return false;
        }

        $text = $this->text($value);

        if ($text === null) {
            return null;
        }

        if (is_numeric($text)) {
            return $this->parseDate((float) $text);
        }

        foreach (self::DATE_FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $text);
            $errors = DateTimeImmutable::getLastErrors();

            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                $date = CarbonImmutable::instance($date)->startOfDay();

                return $date->year >= 2000 && $date->year < 2100 ? $date : false;
            }
        }

        return false;
    }

    private function number(int $row, string $column, mixed $value, ImportReport $report): ?float
    {
        if (is_int($value) || is_float($value)) {
            $number = (float) $value;
        } else {
            $text = $this->text($value);

            if ($text === null) {
                return null;
            }

            $candidate = str_replace([' ', ','], ['', '.'], $text);

            if (! is_numeric($candidate)) {
                $report->warning($row, $column, 'Not a number; left empty.', $text);

                return null;
            }

            $number = (float) $candidate;
        }

        if ($number < 0) {
            $report->warning($row, $column, 'Negative value; left empty.', $number);

            return null;
        }

        return $number;
    }

    private function netType(mixed $value, ImportReport $report): ?string
    {
        $text = $this->text($value);

        if ($text === null) {
            return null;
        }

        $canonical = self::NET_TYPES[mb_strtolower($text)] ?? $text;

        if ($canonical !== $text) {
            $report->normalised("net type '{$text}' → {$canonical}");
        }

        return $canonical;
    }

    private function tag(mixed $value, ImportReport $report): ?string
    {
        if (is_int($value) || is_float($value)) {
            $report->normalised('tag number digits → GNP-<digits>');

            return TagNumber::normalize($value);
        }

        $text = $this->text($value);

        if ($text === null) {
            if ($value !== null && trim((string) $value) !== '') {
                $report->normalised("tag placeholder '".trim((string) $value)."' → empty");
            }

            return null;
        }

        $tag = TagNumber::normalize($text);

        if ($tag !== $text) {
            $report->normalised(ctype_digit($text) ? 'tag number digits → GNP-<digits>' : 'tag number spacing/case cleaned (e.g. GLS - 2511017 → GLS-2511017)');
        }

        return $tag;
    }

    private function containerType(mixed $value, ImportReport $report): ?string
    {
        $type = $this->upper($value);

        if ($type === 'AIR') {
            $report->normalised("container type 'AIR' → empty (air waybill)");

            return null;
        }

        return $type;
    }
}
