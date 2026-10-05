<?php

namespace App\Tracking;

use App\Enums\CargoStatus;
use App\Models\CargoItem;
use App\Models\Shipment;
use App\Services\Shipments\ChangeContext;
use App\Services\Shipments\DateChangeService;
use App\Services\Shipments\StatusService;
use App\Support\Dates;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class TrackingSyncService
{
    public function __construct(
        private readonly TrackingProviderRegistry $providers,
        private readonly StatusService $statuses,
        private readonly DateChangeService $dates,
    ) {}

    public function sync(Shipment $shipment): TrackingSyncResult
    {
        $result = new TrackingSyncResult;

        foreach ($this->providers->enabled() as $provider) {
            if (! $provider->supports($shipment)) {
                continue;
            }

            $result->providers[] = $provider->name();

            foreach ($provider->fetchUpdates($shipment) as $update) {
                $this->apply($shipment->refresh(), $provider->name(), $update, $result);
            }
        }

        return $result;
    }

    private function apply(Shipment $shipment, string $provider, TrackingUpdate $update, TrackingSyncResult $result): void
    {
        $items = $this->itemsFor($shipment, $update);

        if ($items->isEmpty()) {
            $result->skipped($provider, $update, 'No matching cargo in this shipment.');

            return;
        }

        $context = ChangeContext::provider($provider, $update->description, $update->occurredAt);

        $this->applyDates($shipment, $items, $update, $context, $provider, $result);

        if ($update->status !== null) {
            $this->applyStatus($shipment, $items, $update, $context, $provider, $result);
        }
    }

    private function applyDates(Shipment $shipment, Collection $items, TrackingUpdate $update, ChangeContext $context, string $provider, TrackingSyncResult $result): void
    {
        $dates = array_filter(
            ['etd' => $update->etd, 'eta' => $update->eta],
            fn ($date, string $field) => $date !== null && ! Dates::sameDay($shipment->{$field}, $date),
            ARRAY_FILTER_USE_BOTH,
        );

        try {
            if ($dates !== []) {
                $this->dates->changeShipmentDates($shipment, $dates, $context);
                $result->applied($provider, $update, 'dates: '.implode(', ', array_map(
                    fn (string $field) => strtoupper($field).' '.$dates[$field]->format('d.m.Y'),
                    array_keys($dates),
                )));
            }

            $delivery = $update->customerDeliveryDate;
            $changing = $delivery === null ? collect() : $items->reject(fn (CargoItem $item) => Dates::sameDay($item->customer_delivery_date, $delivery));

            if ($changing->isNotEmpty()) {
                $this->dates->changeDeliveryDates($shipment, $changing, $delivery, $context);
                $result->applied($provider, $update, "customer delivery date {$delivery->format('d.m.Y')}");
            }
        } catch (ValidationException $e) {
            $result->skipped($provider, $update, $this->firstMessage($e));
        }
    }

    private function applyStatus(Shipment $shipment, Collection $items, TrackingUpdate $update, ChangeContext $context, string $provider, TrackingSyncResult $result): void
    {
        $target = $update->status;
        $behind = $items->filter(fn (CargoItem $item) => $item->status->isBefore($target));

        if ($behind->isEmpty()) {
            return;
        }

        foreach ($behind->groupBy(fn (CargoItem $item) => $item->status->value) as $from => $group) {
            foreach (CargoStatus::from($from)->pathTo($target) ?? [] as $step) {
                $implied = $step !== $target;

                try {
                    $this->statuses->change(
                        $shipment,
                        $group,
                        $step,
                        $implied ? $context->asImplied() : $context,
                        itemScope: $update->targetsPackages(),
                        recordActualDates: ! $implied,
                    );
                    $result->applied($provider, $update, "status {$step->label()} ({$group->count()} items)");
                } catch (ValidationException $e) {
                    $result->skipped($provider, $update, $this->firstMessage($e));

                    break;
                }
            }
        }
    }

    private function itemsFor(Shipment $shipment, TrackingUpdate $update): Collection
    {
        return $shipment->cargoQuery()
            ->when($update->containerNo, fn ($q, string $containerNo) => $q->whereHas(
                'container',
                fn ($container) => $container->where('container_no', $containerNo),
            ))
            ->when($update->targetsPackages(), fn ($q) => $q->whereIn('package_no', $update->packageNos))
            ->get();
    }

    private function firstMessage(ValidationException $e): string
    {
        return collect($e->errors())->flatten()->first() ?? $e->getMessage();
    }
}
