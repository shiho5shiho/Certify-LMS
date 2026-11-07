<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Exceptions\MeetingQuota\InvalidStripeWebhookException;
use App\Exceptions\MeetingQuota\StripeRequestFailedException;
use App\Models\MeetingPack;
use App\Models\User;
use Stripe\Event;

/**
 *  Stripe との通信(決済画面の作成 / Webhook の署名検証)の契約。
 *
 * Stripe API の呼び出しはすべてこの契約の実装に閉じ込める。
 * テスト(T-A-04)ではこの契約をモックに差し替えて、外部通信なしで検証する。
 */
interface StripeServiceContract
{
    /**
     * 面談パックの名前と価格(円)で決済画面(Checkout Session)を作成し、ID と決済画面の URL を返す。
     *
     * @return array{id: string, url: string}
     *
     * @throws StripeRequestFailedException
     */
    public function createCheckoutSession(User $student, MeetingPack $meetingPack, string $successUrl, string $cancelUrl): array;

    /**
     * Webhook のリクエスト本文と署名ヘッダーを検証し、Stripe のイベントとして返す。
     *
     * @throws InvalidStripeWebhookException
     */
    public function constructWebhookEvent(string $payload, string $signature): Event;
}
