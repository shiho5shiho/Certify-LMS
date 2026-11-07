<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MeetingQuota\StripeRequestFailedException;
use App\Models\MeetingPack;
use App\Models\User;
use App\Services\Contracts\StripeServiceContract;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Stripe API を呼び出す StripeServiceContract の実装。
 *
 * - 価格は Stripe の Price ID を使わず、面談パックの名前と価格をその都度渡す(PM 確認 Q1)
 * - 支払い方法は指定せず、Stripe 側で設定されている決済方法を使う(PM 確認 Q2)
 * - 通信の失敗は StripeRequestFailedException に変換する
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

    private function makeClient(): StripeClient
    {
        $secret = config('services.stripe.secret');

        if (! is_string($secret) || $secret === '') {
            throw new StripeRequestFailedException('Stripe のシークレットキーが設定されていません。');
        }

        return new StripeClient($secret);
    }
}
