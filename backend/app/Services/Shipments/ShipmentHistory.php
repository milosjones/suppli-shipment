<?php

namespace App\Services\Shipments;

use App\Models\Shipment;
use App\Models\ShipmentDateChange;
use App\Models\ShipmentStatusChange;

final class ShipmentHistory
{
    public function timeline(Shipment $shipment, ?string $type = null): array
    {
        $groups = [];

        if ($type !== 'dates') {
            $statusChanges = $shipment->statusChanges()
                ->with(['user:id,name', 'container:id,container_no', 'cargoItem:id,package_no'])
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->get();

            foreach ($statusChanges as $change) {
                $this->addStatusChange($groups, $change);
            }
        }

        if ($type !== 'status') {
            $dateChanges = $shipment->dateChanges()
                ->with(['user:id,name', 'container:id,container_no', 'cargoItem:id,package_no'])
                ->orderBy('id')
                ->get();

            foreach ($dateChanges as $change) {
                $this->addDateChange($groups, $change);
            }
        }

        $timeline = array_map(function (array $group): array {
            $group['status_changes'] = array_values($group['status_changes']);
            $group['date_changes'] = array_values($group['date_changes']);

            return $group;
        }, array_values($groups));

        usort($timeline, fn (array $a, array $b) => [$b['at'], $b['recorded_at']] <=> [$a['at'], $a['recorded_at']]);

        return $timeline;
    }

    private function addStatusChange(array &$groups, ShipmentStatusChange $change): void
    {
        $group = &$this->group($groups, $change->change_group, $change->occurred_at->toIso8601String(), $change);

        $key = ($change->from_status?->value ?? 'none').'>'.$change->to_status->value.($change->is_correction ? ':c' : '');
        $entry = &$group['status_changes'][$key];
        $entry ??= [
            'from' => $change->from_status?->value,
            'to' => $change->to_status->value,
            'is_correction' => $change->is_correction,
            'reason' => $change->reason,
            'source' => $change->source->value,
            'items_count' => 0,
            'containers' => [],
            'packages' => [],
        ];

        $entry['items_count']++;
        $this->addUnique($entry['containers'], $change->container?->container_no);
        $this->addUnique($entry['packages'], $change->cargoItem?->package_no);

        if ($change->occurred_at->toIso8601String() > $group['at']) {
            $group['at'] = $change->occurred_at->toIso8601String();
        }
    }

    private function addDateChange(array &$groups, ShipmentDateChange $change): void
    {
        $group = &$this->group($groups, $change->change_group, $change->created_at->toIso8601String(), $change);

        $key = implode('|', [
            $change->field->value, $change->old_date?->toDateString(), $change->new_date?->toDateString(),
            $change->source->value, $change->reason,
        ]);
        $entry = &$group['date_changes'][$key];
        $entry ??= [
            'field' => $change->field->value,
            'field_label' => $change->field->label(),
            'old_date' => $change->old_date?->toDateString(),
            'new_date' => $change->new_date?->toDateString(),
            'shift_days' => $change->shift_days,
            'reason' => $change->reason,
            'source' => $change->source->value,
            'items_count' => 0,
            'containers' => [],
            'packages' => [],
        ];

        if ($change->cargo_item_id !== null) {
            $entry['items_count']++;
            $this->addUnique($entry['containers'], $change->container?->container_no);
            $this->addUnique($entry['packages'], $change->cargoItem?->package_no);
        }
    }

    private function &group(array &$groups, string $id, string $at, ShipmentStatusChange|ShipmentDateChange $change): array
    {
        $groups[$id] ??= [
            'change_group' => $id,
            'at' => $at,
            'recorded_at' => $change->created_at->toIso8601String(),
            'source' => $change->source->value,
            'user' => $change->user ? ['id' => $change->user->id, 'name' => $change->user->name] : null,
            'status_changes' => [],
            'date_changes' => [],
        ];

        return $groups[$id];
    }

    private function addUnique(array &$list, ?string $value): void
    {
        if ($value !== null && ! in_array($value, $list, true)) {
            $list[] = $value;
        }
    }
}
