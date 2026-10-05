<?php

namespace App\Models;

use App\Enums\ChangeSource;
use App\Enums\DateField;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\IsAppendOnly;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'shipment_id', 'container_id', 'cargo_item_id', 'change_group', 'field', 'old_date', 'new_date',
    'shift_days', 'reason', 'source', 'user_id', 'import_id',
])]
class ShipmentDateChange extends Model
{
    use BelongsToCompany, IsAppendOnly;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'field' => DateField::class,
            'old_date' => 'immutable_date',
            'new_date' => 'immutable_date',
            'shift_days' => 'integer',
            'source' => ChangeSource::class,
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
