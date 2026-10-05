<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWebhookEndpointRequest;
use App\Http\Requests\Admin\UpdateWebhookEndpointRequest;
use App\Http\Resources\WebhookDeliveryResource;
use App\Http\Resources\WebhookEndpointResource;
use App\Models\WebhookEndpoint;
use App\Support\Tenancy\TenantContext;
use App\Webhooks\WebhookOutbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class WebhookEndpointController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(): AnonymousResourceCollection
    {
        return WebhookEndpointResource::collection(
            WebhookEndpoint::query()->with('latestDelivery')->orderBy('id')->get(),
        );
    }

    public function store(StoreWebhookEndpointRequest $request): JsonResponse
    {
        $secret = WebhookEndpoint::newSecret();

        $webhook = WebhookEndpoint::create([
            ...$request->validated(),
            'company_id' => $this->tenant->id(),
            'secret' => $secret,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return (new WebhookEndpointResource($webhook->load('latestDelivery')))
            ->withSecret($secret)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateWebhookEndpointRequest $request, WebhookEndpoint $webhook, WebhookOutbox $outbox): WebhookEndpointResource
    {
        $wasActive = $webhook->is_active;
        $webhook->update($request->validated());

        if (! $wasActive && $webhook->is_active) {
            $outbox->resume($webhook);
        }

        return new WebhookEndpointResource($webhook->load('latestDelivery'));
    }

    public function destroy(WebhookEndpoint $webhook): Response
    {
        $webhook->delete();

        return response()->noContent();
    }

    public function rotateSecret(WebhookEndpoint $webhook): WebhookEndpointResource
    {
        $secret = WebhookEndpoint::newSecret();
        $webhook->update(['secret' => $secret]);

        return (new WebhookEndpointResource($webhook->load('latestDelivery')))->withSecret($secret);
    }

    public function test(WebhookEndpoint $webhook, WebhookOutbox $outbox): JsonResponse
    {
        abort_unless($webhook->is_active, Response::HTTP_UNPROCESSABLE_ENTITY, 'This webhook is paused. Resume it to send a test event.');

        return (new WebhookDeliveryResource($outbox->sendTest($webhook)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function deliveries(Request $request, WebhookEndpoint $webhook): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'succeeded', 'failed'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return WebhookDeliveryResource::collection(
            $webhook->deliveries()
                ->with('shipment')
                ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
                ->latest('id')
                ->paginate($filters['per_page'] ?? 20)
                ->withQueryString(),
        );
    }
}
