<?php

namespace App\Services\Shipments;

use App\Enums\DateField;
use App\Models\CargoItem;
use App\Models\Shipment;
use App\Models\ShipmentDateChange;
use App\Support\Dates;
use App\Webhooks\WebhookOutbox;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DateChangeService
{
    public function __construct(private readonly WebhookOutbox $webhooks) {}

    public function changeShipmentDates(Shipment $shipment, array $dates, ChangeContext $context): Collection
    {
        $this->requireReason($context);

        $etd = array_key_exists('etd', $dates) ? $dates['etd'] : $shipment->etd;
        $eta = array_key_exists('eta', $dates) ? $dates['eta'] : $shipment->eta;

        if ($etd !== null && $eta !== null && $etd->gt($eta)) {
            $field = array_key_exists('eta', $dates) ? 'eta' : 'etd';

            throw ValidationException::withMessages([
                $field => "ETD ({$etd->format('d.m.Y')}) cannot be after ETA ({$eta->format('d.m.Y')}).",
            ]);
        }

        $changes = [];

        foreach ([DateField::Etd, DateField::Eta] as $field) {
            if (array_key_exists($field->value, $dates) && ! Dates::sameDay($shipment->{$field->value}, $dates[$field->value])) {
                $changes[$field->value] = [$shipment->{$field->value}, $dates[$field->value]];
            }
        }

        if ($changes === []) {
            throw ValidationException::withMessages(['dates' => 'The new dates are the same as the current ones.']);
        }

        return DB::transaction(function () use ($shipment, $changes, $context): Collection {
            $rows = collect();

            foreach ($changes as $field => [$old, $new]) {
                $rows->push($this->record($shipment, null, DateField::from($field), $old, $new, $context));
                $shipment->{$field} = $new;
            }

            $shipment->save();
            $this->webhooks->record($shipment, $context);

            return $rows;
        });
    }

    public function changeDeliveryDates(Shipment $shipment, Collection $items, ?CarbonImmutable $date, ChangeContext $context): Collection
    {
        $this->requireReason($context);

        if ($date !== null && $shipment->etd !== null && $date->lt($shipment->etd)) {
            throw ValidationException::withMessages([
                'customer_delivery_date' => "The customer delivery date cannot be before the ETD ({$shipment->etd->format('d.m.Y')}).",
            ]);
        }

        $changing = $items->reject(fn (CargoItem $item) => Dates::sameDay($item->customer_delivery_date, $date));

        if ($changing->isEmpty()) {
            throw ValidationException::withMessages([
                'customer_delivery_date' => 'The selected cargo already has this customer delivery date.',
            ]);
        }

        return DB::transaction(function () use ($shipment, $changing, $date, $context): Collection {
            $rows = $changing->map(function (CargoItem $item) use ($shipment, $date, $context): ShipmentDateChange {
                $row = $this->record($shipment, $item, DateField::CustomerDeliveryDate, $item->customer_delivery_date, $date, $context);

                $item->customer_delivery_date = $date;
                $item->save();

                return $row;
            });

            $shipment->touch();
            $this->webhooks->record($shipment, $context);

            return $rows->values();
        });
    }

    private function record(Shipment $shipment, ?CargoItem $item, DateField $field, ?CarbonImmutable $old, ?CarbonImmutable $new, ChangeContext $context): ShipmentDateChange
    {
        return ShipmentDateChange::create([
            'company_id' => $shipment->company_id,
            'shipment_id' => $shipment->id,
            'container_id' => $item?->container_id,
            'cargo_item_id' => $item?->id,
            'change_group' => $context->group,
            'field' => $field,
            'old_date' => $old,
            'new_date' => $new,
            'shift_days' => $old !== null && $new !== null ? Dates::daysBetween($old, $new) : null,
            'reason' => $context->reason,
            'source' => $context->source,
            'user_id' => $context->user?->id,
            'import_id' => $context->importId,
        ]);
    }

    private function requireReason(ChangeContext $context): void
    {
        if (! $context->hasReason()) {
            throw ValidationException::withMessages(['reason' => 'A reason is required when changing dates.']);
        }
    }
}
