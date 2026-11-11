<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MeetingQuota\InvalidStripeWebhookException;
use App\Exceptions\MeetingQuota\StripeRequestFailedException;
use App\Models\MeetingPack;
use App\Models\User;
use App\Services\Contracts\StripeServiceContract;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Stripe API を呼び出す StripeServiceContract の実装。
 *
 * - 価格は Stripe の Price ID を使わず、面談パックの名前と価格をその都度渡す
 * - 支払い方法は指定せず、Stripe 側で設定されている決済方法を使う
 * - 通信の失敗は StripeRequestFailedException に変換する
 * - Webhook は署名シークレットで署名を検証し、検証できない場合は InvalidStripeWebhookException を投げる
 */
final class StripeService implements StripeServiceContract
{
    public function createCheckoutSession(User $student, MeetingPack $meetingPack, string $successUrl, string $cancelUrl): array
    {
        try {
            $session = $this->makeClient()->checkout->sessions->create([
                'mode' => 'payment',
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'jpy',
                        // 円は小数のない通貨のため、価格をそのまま渡す
                        'unit_amount' => $meetingPack->price,
                        'product_data' => ['name' => $meetingPack->name],
                    ],
                    'quantity' => 1,
                ]],
                'customer_email' => $student->email,
                'client_reference_id' => $student->id,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ]);
        } catch (ApiErrorException $e) {
            throw new StripeRequestFailedException(previous: $e);
        }

        return ['id' => $session->id, 'url' => $session->url];
    }

    public function constructWebhookEvent(string $payload, string $signature): Event
    {
        $secret = config('services.stripe.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            throw new InvalidStripeWebhookException('Stripe の Webhook 署名シークレットが設定されていません。');
        }

        try {
            return Webhook::constructEvent($payload, $signature, $secret);
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            throw new InvalidStripeWebhookException(previous: $e);
        }
    }

    private function makeClient(): StripeClient
    {
        $secret = config('services.stripe.secret');

        if (! is_string($secret) || $secret === '') {
            throw new StripeRequestFailedException('Stripe のシークレットキーが設定されていません。');
        }

        return new StripeClient($secret);
    }
}
