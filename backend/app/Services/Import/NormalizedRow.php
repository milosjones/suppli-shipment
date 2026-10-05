<?php

namespace App\Services\Import;

use App\Enums\CargoStatus;
use App\Enums\ProductCategory;
use App\Enums\ShippingMethod;
use Carbon\CarbonImmutable;

final class NormalizedRow
{
    public function __construct(
        public readonly int $rowNumber,
        public readonly string $packageNo,
        public readonly string $containerNo,
        public readonly ShippingMethod $shippingMethod,
        public readonly string $destinationPort,
        public readonly CarbonImmutable $etd,
        public readonly CarbonImmutable $etaPlanned,
        public readonly CargoStatus $status,
        public readonly string $rawStatus,
        public string $customerName = '',
        public readonly ?string $customerPo = null,
        public readonly ?string $proformaInvoiceNo = null,
        public readonly ?string $exporterReference = null,
        public readonly ?string $productCode = null,
        public readonly ?string $netType = null,
        public readonly ?string $productDescription = null,
        public readonly ProductCategory $productCategory = ProductCategory::Other,
        public readonly ?float $quantity = null,
        public readonly ?string $unit = null,
        public readonly ?float $netWeightKg = null,
        public readonly ?float $grossWeightKg = null,
        public readonly ?string $tagNumber = null,
        public readonly ?string $sealNo = null,
        public readonly ?string $containerType = null,
        public readonly ?string $shippingLine = null,
        public readonly ?string $incoterm = null,
        public readonly ?CarbonImmutable $etaActual = null,
        public readonly ?CarbonImmutable $customerApprovedDeliveryDate = null,
        public readonly ?CarbonImmutable $storageDate = null,
        public readonly ?string $storageFacility = null,
        public readonly ?CarbonImmutable $customerDeliveryDate = null,
        public readonly ?string $comments = null,
    ) {}

    public function voyageKey(): string
    {
        return implode('|', [
            $this->shippingMethod->value,
            mb_strtoupper((string) $this->shippingLine),
            $this->destinationPort,
            $this->etd->toDateString(),
            $this->etaPlanned->toDateString(),
        ]);
    }
}
