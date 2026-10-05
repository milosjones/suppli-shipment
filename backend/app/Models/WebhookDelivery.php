<?php

namespace App\Models;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'webhook_endpoint_id', 'event_id', 'change_group', 'event_type', 'shipment_id', 'payload', 'status',
    'attempts', 'next_attempt_at', 'last_attempt_at', 'delivered_at', 'response_status', 'response_body', 'error',
])]
class WebhookDelivery extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'event_type' => WebhookEvent::class,
            'payload' => 'array',
            'status' => WebhookDeliveryStatus::class,
            'attempts' => 'integer',
            'response_status' => 'integer',
            'next_attempt_at' => 'immutable_datetime',
            'last_attempt_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
        ];
    }

    public static function maxAttempts(): int
    {
        return count(config('webhooks.retry_delays')) + 1;
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
