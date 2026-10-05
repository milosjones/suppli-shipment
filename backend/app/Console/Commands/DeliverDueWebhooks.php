<?php

namespace App\Console\Commands;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use App\Webhooks\WebhookOutbox;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class DeliverDueWebhooks extends Command
{
    protected $signature = 'webhooks:deliver-due';

    protected $description = 'Queue webhook deliveries whose next attempt is overdue, e.g. after a crash or a stopped worker (schedule this)';

    public function handle(WebhookOutbox $outbox): int
    {
        $ids = WebhookDelivery::query()
            ->where('status', WebhookDeliveryStatus::Pending)
            ->where('next_attempt_at', '<=', CarbonImmutable::now()->subMinutes(config('webhooks.overdue_after_minutes')))
            ->whereHas('endpoint', fn ($query) => $query->where('is_active', true))
            ->orderBy('next_attempt_at')
            ->limit(500)
            ->pluck('id');

        $outbox->dispatch($ids);
        $this->info("Queued {$ids->count()} overdue webhook deliveries.");

        return self::SUCCESS;
    }
}
