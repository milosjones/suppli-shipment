<?php

namespace App\Http\Resources;

use App\Models\WebhookDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'event_type' => $this->event_type,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'max_attempts' => WebhookDelivery::maxAttempts(),
            'next_attempt_at' => $this->next_attempt_at?->toIso8601String(),
            'last_attempt_at' => $this->last_attempt_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'response_status' => $this->response_status,
            'response_body' => $this->response_body,
            'error' => $this->error,
            'shipment' => $this->whenLoaded('shipment', fn () => [
                'id' => $this->shipment->id,
                'reference' => $this->shipment->reference,
            ]),
            'payload' => $this->payload,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
