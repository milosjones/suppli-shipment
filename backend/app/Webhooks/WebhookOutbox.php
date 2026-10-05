<?php

namespace App\Webhooks;

use App\Enums\ChangeSource;
use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Shipment;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Shipments\ChangeContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class WebhookOutbox
{
    private array $queuedGroups = [];

    public function __construct(private readonly WebhookPayloads $payloads) {}

    public function record(Shipment $shipment, ChangeContext $context): void
    {
        if ($context->source === ChangeSource::Import) {
            return;
        }

        $endpoints = WebhookEndpoint::query()
            ->where('company_id', $shipment->company_id)
            ->where('is_active', true)
            ->get();

        if ($endpoints->isEmpty()) {
            return;
        }

        foreach ($this->payloads->forChangeGroup($shipment, $context->group) as $payload) {
            $event = WebhookEvent::from($payload['type']);
            $this->store($endpoints->filter(fn (WebhookEndpoint $endpoint) => $endpoint->subscribesTo($event)), $payload, $shipment->id, $context->group);
        }

        if (isset($this->queuedGroups[$context->group])) {
            return;
        }

        $this->queuedGroups[$context->group] = true;

        DB::afterCommit(fn () => $this->dispatch(
            WebhookDelivery::query()
                ->where('change_group', $context->group)
                ->where('status', WebhookDeliveryStatus::Pending)
                ->pluck('id'),
        ));
    }

    public function sendTest(WebhookEndpoint $endpoint): WebhookDelivery
    {
        $payload = $this->payloads->test($endpoint);
        $this->store(collect([$endpoint]), $payload, null, null);

        $delivery = $endpoint->deliveries()->where('event_id', $payload['id'])->sole();
        $this->dispatch(collect([$delivery->id]));

        return $delivery->refresh();
    }

    public function redeliver(WebhookDelivery $delivery): void
    {
        $delivery->forceFill([
            'status' => WebhookDeliveryStatus::Pending,
            'attempts' => 0,
            'next_attempt_at' => CarbonImmutable::now(),
            'delivered_at' => null,
            'error' => null,
        ])->save();

        $this->dispatch(collect([$delivery->id]));
    }

    public function resume(WebhookEndpoint $endpoint): void
    {
        $ids = $endpoint->deliveries()
            ->where('status', WebhookDeliveryStatus::Pending)
            ->whereNull('next_attempt_at')
            ->pluck('id');

        WebhookDelivery::query()->whereKey($ids)->update(['next_attempt_at' => CarbonImmutable::now()]);

        $this->dispatch($ids);
    }

    public function dispatch(Collection $deliveryIds): void
    {
        $deliveryIds->each(fn (int $id) => DeliverWebhook::dispatch($id));
    }

    private function store(Collection $endpoints, array $payload, ?int $shipmentId, ?string $group): void
    {
        if ($endpoints->isEmpty()) {
            return;
        }

        $now = CarbonImmutable::now();
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        WebhookDelivery::query()->upsert(
            $endpoints->map(fn (WebhookEndpoint $endpoint) => [
                'company_id' => $endpoint->company_id,
                'webhook_endpoint_id' => $endpoint->id,
                'event_id' => $payload['id'],
                'change_group' => $group,
                'event_type' => $payload['type'],
                'shipment_id' => $shipmentId,
                'payload' => $body,
                'status' => WebhookDeliveryStatus::Pending->value,
                'attempts' => 0,
                'next_attempt_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all(),
            ['webhook_endpoint_id', 'event_id'],
            ['payload', 'updated_at'],
        );
    }
}
