<?php

namespace App\Services\Shipments;

use App\Enums\CargoStatus;
use App\Models\CargoItem;
use App\Models\Shipment;
use App\Models\ShipmentStatusChange;
use App\Support\Dates;
use App\Webhooks\WebhookOutbox;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StatusService
{
    public function __construct(
        private readonly DateChangeService $dates,
        private readonly ShipmentStatusRefresher $refresher,
        private readonly WebhookOutbox $webhooks,
    ) {}

    public function change(
        Shipment $shipment,
        Collection $items,
        CargoStatus $target,
        ChangeContext $context,
        bool $itemScope = false,
        bool $correction = false,
        bool $recordActualDates = true,
    ): StatusChangeResult {
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['status' => 'No cargo items selected.']);
        }

        if ($itemScope && ! $target->canTargetSingleItems()) {
            throw ValidationException::withMessages([
                'status' => "\"{$target->label()}\" applies to whole containers. Select the container or the shipment instead of single items.",
            ]);
        }

        if ($correction && ! $context->hasReason()) {
            throw ValidationException::withMessages(['reason' => 'A reason is required when correcting a status.']);
        }

        $occurredAt = $context->occurredAt ?? CarbonImmutable::now();
        $this->validateOccurredAt($shipment, $target, $occurredAt, $correction);

        $changing = collect();
        $problems = [];

        foreach ($items as $item) {
            if ($item->status === $target) {
                continue;
            }

            $allowed = $correction ? $target->isBefore($item->status) : $item->status->canMoveTo($target);

            if ($allowed) {
                $changing->push($item);
            } else {
                $problems[] = $correction
                    ? "{$item->package_no}: a correction can only move the status backwards (currently {$item->status->label()})."
                    : "{$item->package_no}: cannot move from {$item->status->label()} to {$target->label()}.";
            }
        }

        if ($problems !== []) {
            throw ValidationException::withMessages(['items' => $problems]);
        }

        if ($changing->isEmpty()) {
            throw ValidationException::withMessages(['status' => "The selected cargo is already {$target->label()}."]);
        }

        DB::transaction(function () use ($shipment, $changing, $target, $context, $occurredAt, $correction, $recordActualDates): void {
            $now = CarbonImmutable::now();

            ShipmentStatusChange::insert($changing->map(fn (CargoItem $item) => [
                'company_id' => $item->company_id,
                'shipment_id' => $shipment->id,
                'container_id' => $item->container_id,
                'cargo_item_id' => $item->id,
                'change_group' => $context->group,
                'from_status' => $item->status->value,
                'to_status' => $target->value,
                'is_correction' => $correction,
                'reason' => $context->reason,
                'source' => $context->source->value,
                'user_id' => $context->user?->id,
                'import_id' => $context->importId,
                'occurred_at' => $occurredAt,
                'created_at' => $now,
            ])->all());

            CargoItem::query()
                ->whereKey($changing->pluck('id')->all())
                ->update(['status' => $target->value, 'raw_status' => null, 'updated_at' => $now]);

            foreach ($changing as $item) {
                $item->forceFill(['status' => $target, 'raw_status' => null])->syncOriginal();
            }

            if ($recordActualDates && ! $correction) {
                $this->recordActualDates($shipment, $changing, $target, $occurredAt, $context);
            }

            $this->refresher->refresh($shipment);
            $this->webhooks->record($shipment, $context);
        });

        return new StatusChangeResult($context->group, $changing->count(), $items->count() - $changing->count());
    }

    private function recordActualDates(Shipment $shipment, Collection $items, CargoStatus $target, CarbonImmutable $occurredAt, ChangeContext $context): void
    {
        $day = $occurredAt->setTimezone(config('shipments.timezone'))->startOfDay();
        $label = $day->format('d.m.Y');

        if ($target === CargoStatus::InTransit && ! Dates::sameDay($shipment->etd, $day)) {
            $this->dates->changeShipmentDates($shipment, ['etd' => $day], $context->asSystem("Departure recorded on {$label}"));
        }

        if ($target === CargoStatus::Arrived && ! Dates::sameDay($shipment->eta, $day)) {
            $this->dates->changeShipmentDates($shipment, ['eta' => $day], $context->asSystem("Arrival recorded on {$label}"));
        }

        if ($target === CargoStatus::Delivered) {
            $changing = $items->reject(fn (CargoItem $item) => Dates::sameDay($item->customer_delivery_date, $day));

            if ($changing->isNotEmpty()) {
                $this->dates->changeDeliveryDates($shipment, $changing, $day, $context->asSystem("Delivery recorded on {$label}"));
            }
        }
    }

    private function validateOccurredAt(Shipment $shipment, CargoStatus $target, CarbonImmutable $occurredAt, bool $correction): void
    {
        if ($occurredAt->gt(CarbonImmutable::now()->addMinutes(5))) {
            throw ValidationException::withMessages(['occurred_at' => 'The date of the status change cannot be in the future.']);
        }

        $day = $occurredAt->setTimezone(config('shipments.timezone'))->startOfDay();

        if (! $correction && $target !== CargoStatus::InTransit && $shipment->etd !== null && $day->lt($shipment->etd)) {
            throw ValidationException::withMessages([
                'occurred_at' => "The date cannot be before the shipment's ETD ({$shipment->etd->format('d.m.Y')}).",
            ]);
        }
    }
}
