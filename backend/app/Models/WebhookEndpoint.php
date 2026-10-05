<?php

namespace App\Models;

use App\Enums\WebhookEvent;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable(['company_id', 'url', 'secret', 'events', 'description', 'is_active', 'created_by'])]
#[Hidden(['secret'])]
class WebhookEndpoint extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public static function newSecret(): string
    {
        return 'whsec_'.Str::random(40);
    }

    public function subscribesTo(WebhookEvent $event): bool
    {
        return $event === WebhookEvent::Test || in_array($event->value, $this->events, true);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function latestDelivery(): HasOne
    {
        return $this->hasOne(WebhookDelivery::class)->latestOfMany();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
