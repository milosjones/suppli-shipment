<?php

namespace App\Models;

use App\Enums\CargoStatus;
use App\Enums\ShippingMethod;
use App\Models\Concerns\BelongsToCompany;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable([
    'company_id', 'reference', 'external_reference', 'source_key', 'shipping_method', 'shipping_line',
    'origin_port', 'destination_port', 'incoterm', 'etd', 'eta', 'status', 'notes', 'created_by',
])]
class Shipment extends Model
{
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'shipping_method' => ShippingMethod::class,
            'status' => CargoStatus::class,
            'etd' => 'immutable_date',
            'etd_original' => 'immutable_date',
            'eta' => 'immutable_date',
            'eta_original' => 'immutable_date',
            'etd_delay_days' => 'integer',
            'eta_delay_days' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Shipment $shipment): void {
            $shipment->etd_original ??= $shipment->etd;
            $shipment->eta_original ??= $shipment->eta;
            $shipment->etd_delay_days = Dates::daysBetween($shipment->etd_original, $shipment->etd);
            $shipment->eta_delay_days = Dates::daysBetween($shipment->eta_original, $shipment->eta);
        });
    }

    public function containers(): HasMany
    {
        return $this->hasMany(Container::class)->orderBy('container_no');
    }

    public function cargoItems(): HasManyThrough
    {
        return $this->hasManyThrough(CargoItem::class, Container::class);
    }

    public function cargoQuery(): Builder
    {
        return CargoItem::query()->whereIn('container_id', Container::query()->select('id')->where('shipment_id', $this->id));
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(ShipmentStatusChange::class);
    }

    public function dateChanges(): HasMany
    {
        return $this->hasMany(ShipmentDateChange::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOverdue(?CarbonImmutable $asOf = null): bool
    {
        return $this->overdueDays($asOf) > 0;
    }

    public function overdueDays(?CarbonImmutable $asOf = null): int
    {
        if ($this->eta === null || ! in_array($this->status, CargoStatus::notArrived(), true)) {
            return 0;
        }

        return max(0, Dates::daysBetween($this->eta, $asOf ?? Dates::today()));
    }

    public function hasOnlyPlannedCargo(): bool
    {
        return $this->cargoItems()->where('cargo_items.status', '!=', CargoStatus::Planned)->doesntExist();
    }
}
