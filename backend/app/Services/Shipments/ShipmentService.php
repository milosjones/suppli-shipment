<?php

namespace App\Services\Shipments;

use App\Enums\CargoStatus;
use App\Enums\ProductCategory;
use App\Enums\ShippingMethod;
use App\Models\CargoItem;
use App\Models\Container;
use App\Models\Shipment;
use App\Models\ShipmentStatusChange;
use App\Support\ContainerNumber;
use App\Support\Dates;
use App\Support\TagNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ShipmentService
{
    public function __construct(private readonly ShipmentStatusRefresher $refresher) {}

    public function create(array $data, ChangeContext $context): Shipment
    {
        $initialStatus = CargoStatus::from($data['initial_status'] ?? CargoStatus::Planned->value);

        return DB::transaction(function () use ($data, $context, $initialStatus): Shipment {
            [$id, $reference] = ShipmentReference::next();

            $shipment = new Shipment($this->shipmentAttributes($data));
            $shipment->id = $id;
            $shipment->reference = $reference;
            $shipment->external_reference = $data['external_reference'] ?? null;
            $shipment->status = $initialStatus;
            $shipment->created_by = $context->user?->id;
            $shipment->save();

            foreach ($data['containers'] as $container) {
                $this->createContainer($shipment, $container, $initialStatus, $context);
            }

            $this->refresher->refresh($shipment);

            return $shipment;
        });
    }

    public function update(Shipment $shipment, array $data): Shipment
    {
        if (isset($data['shipping_method'])) {
            $method = ShippingMethod::from($data['shipping_method']);
            $invalid = $shipment->containers()->pluck('container_no')
                ->reject(fn (string $number) => ContainerNumber::isValidFor($method, $number));

            if ($invalid->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'shipping_method' => "These numbers are not valid for {$method->label()} shipments: {$invalid->implode(', ')}.",
                ]);
            }
        }

        $shipment->fill($this->shipmentAttributes($data, partial: true));

        if (array_key_exists('external_reference', $data)) {
            $shipment->external_reference = $data['external_reference'];
        }

        $shipment->save();

        return $shipment;
    }

    public function delete(Shipment $shipment): void
    {
        if (! $shipment->hasOnlyPlannedCargo()) {
            throw ValidationException::withMessages([
                'shipment' => 'Only shipments that have not sailed yet can be deleted; their history must be kept.',
            ]);
        }

        $shipment->delete();
    }

    public function addContainer(Shipment $shipment, array $data, ChangeContext $context): Container
    {
        if (! $shipment->hasOnlyPlannedCargo()) {
            throw ValidationException::withMessages([
                'shipment' => 'Containers can only be added before the shipment sails.',
            ]);
        }

        return DB::transaction(function () use ($shipment, $data, $context): Container {
            $container = $this->createContainer($shipment, $data, CargoStatus::Planned, $context);
            $this->refresher->refresh($shipment);

            return $container;
        });
    }

    public function updateContainer(Container $container, array $data): Container
    {
        if (isset($data['container_no'])) {
            $data['container_no'] = ContainerNumber::normalize($data['container_no']);

            if ($data['container_no'] !== $container->container_no && ! $container->hasOnlyPlannedCargo()) {
                throw ValidationException::withMessages([
                    'container_no' => 'The container number cannot change after the container has sailed.',
                ]);
            }
        }

        if (array_key_exists('container_type', $data)) {
            $data['container_type'] = $this->upper($data['container_type']);
        }

        $container->fill(array_intersect_key($data, array_flip(['container_no', 'seal_no', 'container_type'])))->save();
        $container->shipment->touch();

        return $container;
    }

    public function deleteContainer(Container $container): void
    {
        $shipment = $container->shipment;

        if (! $container->hasOnlyPlannedCargo()) {
            throw ValidationException::withMessages(['container' => 'Only containers that have not sailed can be removed.']);
        }

        if ($shipment->containers()->count() <= 1) {
            throw ValidationException::withMessages(['container' => 'A shipment needs at least one container; delete the shipment instead.']);
        }

        DB::transaction(function () use ($container, $shipment): void {
            $container->delete();
            $this->refresher->refresh($shipment);
        });
    }

    public function addItems(Container $container, array $items, ChangeContext $context): Collection
    {
        if (! $container->hasOnlyPlannedCargo()) {
            throw ValidationException::withMessages(['container' => 'Cargo can only be added before the container sails.']);
        }

        return DB::transaction(function () use ($container, $items, $context): Collection {
            $created = $this->createItems($container, $items, CargoStatus::Planned, $context);
            $this->refresher->refresh($container->shipment);

            return $created;
        });
    }

    private function createContainer(Shipment $shipment, array $data, CargoStatus $status, ChangeContext $context): Container
    {
        $container = $shipment->containers()->create([
            'company_id' => $shipment->company_id,
            'container_no' => ContainerNumber::normalize($data['container_no']),
            'seal_no' => $data['seal_no'] ?? null,
            'container_type' => $this->upper($data['container_type'] ?? null),
        ]);

        $this->createItems($container, $data['items'], $status, $context);

        return $container;
    }

    private function createItems(Container $container, array $items, CargoStatus $status, ChangeContext $context): Collection
    {
        $created = collect($items)->map(fn (array $item) => $container->cargoItems()->create([
            'company_id' => $container->company_id,
            'package_no' => trim($item['package_no']),
            'proforma_invoice_no' => $item['proforma_invoice_no'] ?? null,
            'exporter_reference' => $item['exporter_reference'] ?? null,
            'tag_number' => TagNumber::normalize($item['tag_number'] ?? null),
            'product_code' => $item['product_code'] ?? null,
            'product_description' => $item['product_description'] ?? null,
            'product_category' => $item['product_category']
                ?? ProductCategory::infer($item['net_type'] ?? null, $item['product_description'] ?? null),
            'net_type' => $item['net_type'] ?? null,
            'customer_name' => $item['customer_name'] ?? null,
            'customer_reference' => $item['customer_reference'] ?? null,
            'customer_po' => $item['customer_po'] ?? null,
            'storage_facility' => $item['storage_facility'] ?? null,
            'quantity' => $item['quantity'] ?? null,
            'unit' => $item['unit'] ?? null,
            'net_weight_kg' => $item['net_weight_kg'] ?? null,
            'gross_weight_kg' => $item['gross_weight_kg'] ?? null,
            'status' => $status,
            'customer_delivery_date' => Dates::parse($item['customer_delivery_date'] ?? null),
            'notes' => $item['notes'] ?? null,
        ]));

        $now = CarbonImmutable::now();

        ShipmentStatusChange::insert($created->map(fn (CargoItem $item) => [
            'company_id' => $item->company_id,
            'shipment_id' => $container->shipment_id,
            'container_id' => $container->id,
            'cargo_item_id' => $item->id,
            'change_group' => $context->group,
            'from_status' => null,
            'to_status' => $status->value,
            'is_correction' => false,
            'reason' => $context->reason ?? 'Registered',
            'source' => $context->source->value,
            'user_id' => $context->user?->id,
            'import_id' => $context->importId,
            'occurred_at' => $now,
            'created_at' => $now,
        ])->all());

        return $created;
    }

    private function shipmentAttributes(array $data, bool $partial = false): array
    {
        $attributes = [
            'shipping_method' => $data['shipping_method'] ?? null,
            'shipping_line' => $data['shipping_line'] ?? null,
            'origin_port' => $this->upper($data['origin_port'] ?? null) ?? ($partial ? null : config('shipments.default_origin_port')),
            'destination_port' => $this->upper($data['destination_port'] ?? null),
            'incoterm' => $this->upper($data['incoterm'] ?? null),
            'etd' => Dates::parse($data['etd'] ?? null),
            'eta' => Dates::parse($data['eta'] ?? null),
            'notes' => $data['notes'] ?? null,
        ];

        return $partial
            ? array_intersect_key($attributes, array_flip(array_diff(array_keys($data), ['etd', 'eta'])))
            : $attributes;
    }

    private function upper(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === null || $value === '' ? null : mb_strtoupper($value);
    }
}
