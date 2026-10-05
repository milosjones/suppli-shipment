<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CargoItemResource;
use App\Http\Resources\ShipmentResource;
use App\Services\Reports\DelayedShipmentsReport;
use App\Services\Reports\StatusOverviewReport;
use App\Services\Reports\UpcomingDeliveriesReport;
use App\Services\Shipments\ShipmentSummaries;
use App\Support\Dates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function __construct(private readonly ShipmentSummaries $summaries) {}

    public function upcomingDeliveries(Request $request, UpcomingDeliveriesReport $report): JsonResponse
    {
        $data = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', Rule::when($request->filled('from'), 'after_or_equal:from')],
            'as_of' => ['sometimes', 'date_format:Y-m-d'],
            'destination_port' => ['sometimes', 'string', 'max:100'],
            'customer' => ['sometimes', 'string', 'max:255'],
        ]);

        $today = Dates::today();
        $from = Dates::parse($data['from'] ?? null) ?? $today;
        $to = Dates::parse($data['to'] ?? null) ?? $from->addDays(30);
        $asOf = Dates::parse($data['as_of'] ?? null) ?? $today;

        $result = $report->generate($from, $to, $asOf, Arr::only($data, ['destination_port', 'customer']));
        $this->summaries->attach($result['arrivals']->concat($result['overdue_arrivals']));

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'as_of' => $asOf->toDateString(),
            'arrivals' => ShipmentResource::collection($result['arrivals']),
            'deliveries' => CargoItemResource::collection($result['deliveries']),
            'overdue_arrivals' => ShipmentResource::collection($result['overdue_arrivals']),
            'overdue_deliveries' => CargoItemResource::collection($result['overdue_deliveries']),
        ]);
    }

    public function statusOverview(Request $request, StatusOverviewReport $report): JsonResponse
    {
        $filters = $request->validate([
            'destination_port' => ['sometimes', 'string', 'max:100'],
            'customer' => ['sometimes', 'string', 'max:255'],
        ]);

        return response()->json($report->generate($filters));
    }

    public function delayed(Request $request, DelayedShipmentsReport $report): JsonResponse
    {
        $data = $request->validate([
            'min_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'as_of' => ['sometimes', 'date_format:Y-m-d'],
        ]);

        $result = $report->generate((int) ($data['min_days'] ?? 1), Dates::parse($data['as_of'] ?? null) ?? Dates::today());
        $this->summaries->attach($result['shipments']->pluck('shipment'));

        return response()->json([
            'as_of' => $result['as_of'],
            'min_days' => $result['min_days'],
            'shipments' => $result['shipments']->map(fn (array $row) => [
                ...Arr::except($row, 'shipment'),
                'shipment' => new ShipmentResource($row['shipment']),
            ]),
            'items' => $result['items']->map(fn (array $row) => [
                ...Arr::except($row, 'item'),
                'item' => new CargoItemResource($row['item']),
            ]),
            'at_risk' => $result['at_risk']->map(fn (array $row) => [
                ...Arr::except($row, 'item'),
                'item' => new CargoItemResource($row['item']),
            ]),
        ]);
    }
}
