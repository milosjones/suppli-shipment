<?php

namespace App\Webhooks;

use App\Enums\WebhookEvent;
use App\Models\CargoItem;
use App\Models\Container;
use App\Models\Shipment;
use App\Models\ShipmentDateChange;
use App\Models\ShipmentStatusChange;
use App\Models\WebhookEndpoint;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

final class WebhookPayloads
{
    private const EVENT_NAMESPACE = '0b8f6c1e-5d2a-4c7b-9e3f-6a1d2c3b4e5f';

    public function forChangeGroup(Shipment $shipment, string $group): array
    {
        $statusChanges = ShipmentStatusChange::query()
            ->where('change_group', $group)
            ->whereNotNull('from_status')
            ->with(['cargoItem', 'container', 'user'])
            ->orderBy('id')
            ->get();

        $dateChanges = ShipmentDateChange::query()
            ->where('change_group', $group)
            ->with(['cargoItem', 'container', 'user'])
            ->orderBy('id')
            ->get();

        $events = [];

        if ($statusChanges->isNotEmpty()) {
            $events[] = $this->event(WebhookEvent::StatusChanged, $shipment, $group, $statusChanges, fn (ShipmentStatusChange $change) => [
                ...$this->cargoKeys($change->cargoItem, $change->container),
                'from' => $change->from_status?->value,
                'to' => $change->to_status->value,
                'is_correction' => $change->is_correction,
                'occurred_at' => $change->occurred_at->toIso8601ZuluString(),
            ]);
        }

        if ($dateChanges->isNotEmpty()) {
            $events[] = $this->event(WebhookEvent::DatesChanged, $shipment, $group, $dateChanges, fn (ShipmentDateChange $change) => [
                'field' => $change->field->value,
                ...$this->cargoKeys($change->cargoItem, $change->container),
                'old' => $change->old_date?->toDateString(),
                'new' => $change->new_date?->toDateString(),
                'shift_days' => $change->shift_days,
            ]);
        }

        return $events;
    }

    public function test(WebhookEndpoint $endpoint): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => WebhookEvent::Test->value,
            'created_at' => CarbonImmutable::now()->toIso8601ZuluString(),
            'company' => ['id' => $endpoint->company_id, 'name' => $endpoint->company?->name],
            'message' => 'Test event from Suppli Shipments. If the signature checks out, the webhook is set up correctly.',
        ];
    }

    public static function eventId(string $group, WebhookEvent $type): string
    {
        return Uuid::uuid5(self::EVENT_NAMESPACE, "{$group}/{$type->value}")->toString();
    }

    private function event(WebhookEvent $type, Shipment $shipment, string $group, Collection $rows, callable $change): array
    {
        $first = $rows->first();

        return [
            'id' => self::eventId($group, $type),
            'type' => $type->value,
            'created_at' => CarbonImmutable::now()->toIso8601ZuluString(),
            'change_group' => $group,
            'company' => ['id' => $shipment->company_id, 'name' => $shipment->company?->name],
            'shipment' => [
                'id' => $shipment->id,
                'reference' => $shipment->reference,
                'external_reference' => $shipment->external_reference,
                'status' => $shipment->status?->value,
                'etd' => $shipment->etd?->toDateString(),
                'eta' => $shipment->eta?->toDateString(),
            ],
            'source' => $first->source->value,
            'changed_by' => $first->user?->name,
            'reason' => $first->reason,
            'changes' => $rows->map($change)->values()->all(),
        ];
    }

    private function cargoKeys(?CargoItem $item, ?Container $container): array
    {
        return [
            'package_no' => $item?->package_no,
            'container_no' => $container?->container_no,
            'proforma_invoice_no' => $item?->proforma_invoice_no,
            'tag_number' => $item?->tag_number,
            'customer_reference' => $item?->customer_reference,
        ];
    }
}
