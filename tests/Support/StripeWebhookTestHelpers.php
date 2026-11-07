<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Stripe からの通知(Webhook)を再現するテスト用ヘルパー。
 * Stripe と同じ方法で署名を作り、本物の通知と同じ形で送る。
 */
trait StripeWebhookTestHelpers
{
    /**
     * Stripe と同じ形式の決済画面(Checkout Session)のイベントを組み立てる(決済画面の ID と支払い状態のみ)。
     *
     * @return array<string, mixed>
     */
    protected function stripeCheckoutEvent(string $type, string $checkoutSessionId, string $paymentStatus): array
    {
        return [
            'id' => 'evt_test_'.Str::random(16),
            'object' => 'event',
            'type' => $type,
            'data' => [
                'object' => [
                    'id' => $checkoutSessionId,
                    'object' => 'checkout.session',
                    'payment_status' => $paymentStatus,
                ],
            ],
        ];
    }

    /**
     * Stripe と同じ方法で署名ヘッダーを作る(「タイムスタンプ.本文」の HMAC-SHA256)。
     * タイムスタンプを省略すると現在時刻を使う。
     */
    protected function stripeSignature(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }

    /**
     * 本文と署名ヘッダーを指定して Webhook を送る。署名に null を渡すと署名ヘッダーを付けない。
     */
    protected function postStripeWebhook(string $payload, ?string $signature): TestResponse
    {
        $server = ['CONTENT_TYPE' => 'application/json'];

        if ($signature !== null) {
            $server['HTTP_STRIPE_SIGNATURE'] = $signature;
        }

        return $this->call('POST', route('webhooks.stripe'), server: $server, content: $payload);
    }
}
