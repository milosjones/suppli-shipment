<?php

namespace App\Models;

use App\Enums\CargoStatus;
use App\Enums\ChangeSource;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\IsAppendOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'shipment_id', 'container_id', 'cargo_item_id', 'change_group', 'from_status', 'to_status',
    'is_correction', 'reason', 'source', 'user_id', 'import_id', 'occurred_at',
])]
class ShipmentStatusChange extends Model
{
    use BelongsToCompany, IsAppendOnly;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'from_status' => CargoStatus::class,
            'to_status' => CargoStatus::class,
            'is_correction' => 'boolean',
            'source' => ChangeSource::class,
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function cargoItem(): BelongsTo
    {
        return $this->belongsTo(CargoItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
