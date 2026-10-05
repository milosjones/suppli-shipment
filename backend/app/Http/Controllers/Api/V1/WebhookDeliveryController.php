<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WebhookDeliveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\WebhookDeliveryResource;
use App\Models\WebhookDelivery;
use App\Webhooks\WebhookOutbox;
use Illuminate\Http\Response;

class WebhookDeliveryController extends Controller
{
    public function retry(WebhookDelivery $delivery, WebhookOutbox $outbox): WebhookDeliveryResource
    {
        abort_if($delivery->status === WebhookDeliveryStatus::Pending, Response::HTTP_UNPROCESSABLE_ENTITY, 'This delivery is already waiting to be sent.');
        abort_unless($delivery->endpoint->is_active, Response::HTTP_UNPROCESSABLE_ENTITY, 'This webhook is paused. Resume it to send deliveries again.');

        $outbox->redeliver($delivery);

        return new WebhookDeliveryResource($delivery->refresh()->load('shipment'));
    }
}
