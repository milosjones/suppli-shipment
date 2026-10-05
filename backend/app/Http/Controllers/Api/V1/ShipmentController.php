<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CargoStatus;
use App\Enums\ShippingMethod;
use App\Http\Controllers\Api\V1\Concerns\PresentsShipments;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShipmentRequest;
use App\Http\Requests\UpdateShipmentRequest;
use App\Http\Resources\ShipmentResource;
use App\Models\Shipment;
use App\Services\Shipments\ChangeContext;
use App\Services\Shipments\ShipmentService;
use App\Services\Shipments\ShipmentSummaries;
use App\Support\ContainerNumber;
use App\Support\Dates;
use App\Support\TagNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ShipmentController extends Controller
{
    use PresentsShipments;

    public function __construct(
        private readonly ShipmentService $shipments,
        private readonly ShipmentSummaries $summaries,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        if (is_string($request->query('status'))) {
            $request->merge(['status' => array_filter(explode(',', $request->query('status')))]);
        }

        $filters = $request->validate([
            'status' => ['sometimes', 'array'],
            'status.*' => [Rule::enum(CargoStatus::class)],
            'destination_port' => ['sometimes', 'string', 'max:100'],
            'shipping_method' => ['sometimes', Rule::enum(ShippingMethod::class)],
            'shipping_line' => ['sometimes', 'string', 'max:100'],
            'customer' => ['sometimes', 'string', 'max:255'],
            'eta_from' => ['sometimes', 'date_format:Y-m-d'],
            'eta_to' => ['sometimes', 'date_format:Y-m-d'],
            'delayed' => ['sometimes', 'boolean'],
            'overdue' => ['sometimes', 'boolean'],
            'updated_since' => ['sometimes', 'date'],
            'q' => ['sometimes', 'string', 'max:100'],
            'sort' => ['sometimes', Rule::in([
                'reference', '-reference', 'destination_port', '-destination_port', 'etd', '-etd', 'eta', '-eta',
                'status', '-status', 'eta_delay_days', '-eta_delay_days', 'updated_at', '-updated_at',
            ])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $today = Dates::today()->toDateString();

        $query = Shipment::query()
            ->when($filters['status'] ?? null, fn (Builder $q, array $statuses) => $q->whereIn('status', $statuses))
            ->when($filters['destination_port'] ?? null, fn (Builder $q, string $port) => $q->where('destination_port', mb_strtoupper($port)))
            ->when($filters['shipping_method'] ?? null, fn (Builder $q, string $method) => $q->where('shipping_method', $method))
            ->when($filters['shipping_line'] ?? null, fn (Builder $q, string $line) => $q->where('shipping_line', $line))
            ->when($filters['customer'] ?? null, fn (Builder $q, string $customer) => $q->whereHas(
                'cargoItems',
                fn (Builder $items) => $items->whereLike('cargo_items.customer_name', '%'.$customer.'%'),
            ))
            ->when($filters['eta_from'] ?? null, fn (Builder $q, string $from) => $q->where('eta', '>=', $from))
            ->when($filters['eta_to'] ?? null, fn (Builder $q, string $to) => $q->where('eta', '<=', $to))
            ->when($request->boolean('delayed'), fn (Builder $q) => $q->where(fn (Builder $delayed) => $delayed
                ->where('eta_delay_days', '>', 0)
                ->orWhere('etd_delay_days', '>', 0)
                ->orWhere(fn (Builder $overdue) => $overdue->whereIn('status', CargoStatus::notArrived())->where('eta', '<', $today))))
            ->when($request->boolean('overdue'), fn (Builder $q) => $q->whereIn('status', CargoStatus::notArrived())->where('eta', '<', $today))
            ->when($filters['updated_since'] ?? null, fn (Builder $q, string $since) => $q->where('updated_at', '>=', Dates::eventTime($since)))
            ->when($filters['q'] ?? null, fn (Builder $q, string $search) => $this->search($q, $search));

        $this->sort($query, $filters['sort'] ?? '-eta');

        $page = $query->paginate($filters['per_page'] ?? 25)->withQueryString();
        $this->summaries->attach($page->getCollection());

        return ShipmentResource::collection($page);
    }

    public function store(StoreShipmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! empty($data['external_reference'])) {
            $existing = Shipment::query()->where('external_reference', $data['external_reference'])->first();

            if ($existing !== null) {
                return response()->json([
                    'message' => 'A shipment with this external reference already exists.',
                    'shipment_id' => $existing->id,
                    'reference' => $existing->reference,
                ], Response::HTTP_CONFLICT);
            }
        }

        $shipment = $this->shipments->create($data, ChangeContext::byUser($request->user(), 'Shipment registered'));

        return $this->shipmentDetail($shipment)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Shipment $shipment): ShipmentResource
    {
        return $this->shipmentDetail($shipment);
    }

    public function update(UpdateShipmentRequest $request, Shipment $shipment): ShipmentResource
    {
        return $this->shipmentDetail($this->shipments->update($shipment, $request->validated()));
    }

    public function destroy(Shipment $shipment): Response
    {
        $this->shipments->delete($shipment);

        return response()->noContent();
    }

    private function search(Builder $query, string $search): void
    {
        $like = '%'.$search.'%';
        $tagKey = TagNumber::key($search);

        $query->where(fn (Builder $where) => $where
            ->whereLike('reference', $like)
            ->orWhereLike('external_reference', $like)
            ->orWhereHas('containers', fn (Builder $containers) => $containers
                ->whereLike('container_no', '%'.ContainerNumber::normalize($search).'%'))
            ->orWhereHas('cargoItems', fn (Builder $items) => $items->where(fn (Builder $item) => $item
                ->whereLike('cargo_items.package_no', $like)
                ->orWhereLike('cargo_items.proforma_invoice_no', $like)
                ->orWhereLike('cargo_items.customer_name', $like)
                ->when($tagKey, fn (Builder $tag) => $tag->orWhereLike('cargo_items.tag_key', '%'.$tagKey.'%')))));
    }

    private function sort(Builder $query, string $sort): void
    {
        $column = ltrim($sort, '-');
        $direction = str_starts_with($sort, '-') ? 'DESC' : 'ASC';
        $expression = $column === 'status' ? CargoStatus::rankSql('status') : $column;

        $query->orderByRaw("{$expression} {$direction} NULLS LAST")->orderByDesc('id');
    }
}
