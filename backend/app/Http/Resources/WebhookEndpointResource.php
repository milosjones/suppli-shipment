<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookEndpointResource extends JsonResource
{
    private ?string $plainSecret = null;

    public function withSecret(string $secret): static
    {
        $this->plainSecret = $secret;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'description' => $this->description,
            'events' => $this->events,
            'is_active' => $this->is_active,
            'secret' => $this->when($this->plainSecret !== null, fn () => $this->plainSecret),
            'last_delivery' => $this->whenLoaded('latestDelivery', fn () => new WebhookDeliveryResource($this->latestDelivery)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
