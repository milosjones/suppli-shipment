<?php

namespace App\Jobs;

use App\Webhooks\WebhookSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 12;

    public int $timeout = 60;

    public function __construct(public int $deliveryId) {}

    public function handle(WebhookSender $sender): void
    {
        $retryIn = $sender->attempt($this->deliveryId);

        if ($retryIn !== null) {
            $this->release($retryIn);
        }
    }
}
