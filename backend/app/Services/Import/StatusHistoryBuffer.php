<?php

namespace App\Services\Import;

use App\Enums\CargoStatus;
use App\Enums\ChangeSource;
use App\Models\CargoItem;
use App\Models\Import;
use App\Models\ShipmentStatusChange;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class StatusHistoryBuffer
{
    private array $rows = [];

    private array $groups = [];

    public function __construct(
        private readonly Import $import,
        private readonly ?User $user,
    ) {}

    public function add(CargoItem $item, int $shipmentId, ?CargoStatus $from, CargoStatus $to, CarbonImmutable $day): void
    {
        $groupKey = "{$shipmentId}|{$to->value}|{$day->toDateString()}";
        $this->groups[$groupKey] ??= (string) Str::uuid();

        $this->rows[] = [
            'company_id' => $item->company_id,
            'shipment_id' => $shipmentId,
            'container_id' => $item->container_id,
            'cargo_item_id' => $item->id,
            'change_group' => $this->groups[$groupKey],
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'is_correction' => false,
            'reason' => "Imported from {$this->import->file_name}",
            'source' => ChangeSource::Import->value,
            'user_id' => $this->user?->id,
            'import_id' => $this->import->id,
            'occurred_at' => CarbonImmutable::parse($day->toDateString().' 12:00', config('shipments.timezone')),
            'created_at' => CarbonImmutable::now(),
        ];
    }

    public function flush(): void
    {
        foreach (array_chunk($this->rows, 500) as $chunk) {
            ShipmentStatusChange::insert($chunk);
        }

        $this->rows = [];
    }
}
