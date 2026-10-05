<?php

namespace App\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Models\WebhookDelivery;
use Carbon\CarbonImmutable;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class WebhookSender
{
    public function attempt(int $deliveryId): ?int
    {
        $delivery = WebhookDelivery::query()->with('endpoint')->find($deliveryId);

        if ($delivery?->status !== WebhookDeliveryStatus::Pending) {
            return null;
        }

        if (! $delivery->endpoint->is_active) {
            $delivery->forceFill(['next_attempt_at' => null])->save();

            return null;
        }

        $now = CarbonImmutable::now();

        $claimed = WebhookDelivery::query()
            ->whereKey($deliveryId)
            ->where('status', WebhookDeliveryStatus::Pending)
            ->where('next_attempt_at', '<=', $now)
            ->update([
                'attempts' => DB::raw('attempts + 1'),
                'last_attempt_at' => $now,
                'next_attempt_at' => $now->addSeconds(config('webhooks.lease_seconds')),
                'response_status' => null,
                'response_body' => null,
                'error' => null,
                'updated_at' => $now,
            ]);

        if ($claimed === 0) {
            return null;
        }

        $delivery->refresh();

        [$succeeded, $result] = $this->send($delivery, $now);
        $finished = CarbonImmutable::now();

        if ($succeeded) {
            $delivery->forceFill([
                ...$result,
                'status' => WebhookDeliveryStatus::Succeeded,
                'delivered_at' => $finished,
                'next_attempt_at' => null,
            ])->save();

            return null;
        }

        $delay = config('webhooks.retry_delays')[$delivery->attempts - 1] ?? null;

        $delivery->forceFill([
            ...$result,
            'status' => $delay === null ? WebhookDeliveryStatus::Failed : WebhookDeliveryStatus::Pending,
            'next_attempt_at' => $delay === null ? null : $finished->addSeconds($delay),
        ])->save();

        return $delay;
    }

    public static function signature(string $timestamp, string $body, string $secret): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$body}", $secret);
    }

    private function send(WebhookDelivery $delivery, CarbonImmutable $now): array
    {
        $body = $delivery->getRawOriginal('payload');
        $timestamp = (string) $now->getTimestamp();

        try {
            $response = Http::timeout(config('webhooks.timeout'))
                ->connectTimeout(min(5, config('webhooks.timeout')))
                ->withoutRedirecting()
                ->withUserAgent('SuppliShipments-Webhooks/1.0')
                ->withHeaders([
                    'X-Webhook-Id' => $delivery->event_id,
                    'X-Webhook-Event' => $delivery->event_type->value,
                    'X-Webhook-Timestamp' => $timestamp,
                    'X-Webhook-Attempt' => (string) $delivery->attempts,
                    'X-Webhook-Signature' => 'sha256='.self::signature($timestamp, $body, $delivery->endpoint->secret),
                ])
                ->withBody($body, 'application/json')
                ->post($delivery->endpoint->url);
        } catch (ConnectionException|TransferException $e) {
            return [false, ['response_status' => null, 'response_body' => null, 'error' => $this->limit($e->getMessage())]];
        }

        return [$response->successful(), [
            'response_status' => $response->status(),
            'response_body' => $this->limit($response->body()),
            'error' => $response->successful() ? null : "The endpoint answered HTTP {$response->status()}.",
        ]];
    }

    private function limit(string $text): ?string
    {
        return $text === '' ? null : mb_strcut($text, 0, config('webhooks.response_body_limit'));
    }
}
