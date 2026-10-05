<?php

namespace App\Models;

use App\Enums\CargoStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'shipment_id', 'container_no', 'seal_no', 'container_type'])]
class Container extends Model
{
    use BelongsToCompany, HasFactory;

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function cargoItems(): HasMany
    {
        return $this->hasMany(CargoItem::class)->orderBy('package_no');
    }

    public function status(): ?CargoStatus
    {
        return CargoStatus::leastAdvanced($this->cargoItems->pluck('status'));
    }

    public function statusCounts(): array
    {
        return $this->cargoItems->countBy(fn (CargoItem $item) => $item->status->value)->all();
    }

    public function hasOnlyPlannedCargo(): bool
    {
        return $this->cargoItems()->where('status', '!=', CargoStatus::Planned)->doesntExist();
    }
}
