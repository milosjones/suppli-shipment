<?php

namespace App\Models;

use App\Enums\CargoStatus;
use App\Enums\ProductCategory;
use App\Models\Concerns\BelongsToCompany;
use App\Support\Dates;
use App\Support\TagNumber;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id', 'container_id', 'package_no',
    'proforma_invoice_no', 'exporter_reference', 'tag_number', 'product_code', 'product_description',
    'product_category', 'net_type', 'customer_name', 'customer_reference', 'customer_po', 'storage_facility',
    'quantity', 'unit', 'net_weight_kg', 'gross_weight_kg',
    'status', 'raw_status', 'customer_delivery_date', 'notes',
])]
class CargoItem extends Model
{
    use BelongsToCompany, HasFactory;

    public const REFERENCE_FIELDS = [
        'proforma_invoice_no', 'exporter_reference', 'tag_number', 'product_code', 'product_description',
        'product_category', 'net_type', 'customer_name', 'customer_reference', 'customer_po', 'storage_facility',
        'quantity', 'unit', 'net_weight_kg', 'gross_weight_kg',
    ];

    protected function casts(): array
    {
        return [
            'status' => CargoStatus::class,
            'product_category' => ProductCategory::class,
            'quantity' => 'float',
            'net_weight_kg' => 'float',
            'gross_weight_kg' => 'float',
            'customer_delivery_date' => 'immutable_date',
            'customer_delivery_date_original' => 'immutable_date',
            'delivery_delay_days' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CargoItem $item): void {
            $item->tag_key = TagNumber::key($item->tag_number);
            $item->customer_delivery_date_original ??= $item->customer_delivery_date;
            $item->delivery_delay_days = Dates::daysBetween(
                $item->customer_delivery_date_original,
                $item->customer_delivery_date,
            );
        });
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(ShipmentStatusChange::class);
    }

    public function isDeliveryOverdue(?CarbonImmutable $asOf = null): bool
    {
        return $this->customer_delivery_date !== null
            && $this->status !== CargoStatus::Delivered
            && $this->customer_delivery_date->lt($asOf ?? Dates::today());
    }
}
