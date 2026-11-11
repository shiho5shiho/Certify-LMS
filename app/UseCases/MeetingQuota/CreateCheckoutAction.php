<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Enums\PaymentStatus;
use App\Exceptions\MeetingQuota\StripeRequestFailedException;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use App\Services\Contracts\StripeServiceContract;

/**
 * 受講生の面談パック購入を開始するユースケース。
 *
 * Stripe の決済画面を作成してから、処理中の決済レコードを作成し、決済画面の URL を返す。
 * Stripe との通信に失敗した場合は決済レコードを作らない。
 * 金額・面談回数は購入時点のパックの値を控えとして保存する。残面談回数の加算は Webhook で行う。
 */
final class CreateCheckoutAction
{
    public function __construct(
        private readonly StripeServiceContract $stripe,
    ) {}

    /**
     * @throws StripeRequestFailedException
     */
    public function __invoke(User $student, MeetingPack $meetingPack): string
    {
        $session = $this->stripe->createCheckoutSession(
            $student,
            $meetingPack,
            // {CHECKOUT_SESSION_ID} は Stripe が実際の ID に置き換える。route() のパラメータに渡すと { } がエンコードされるため、文字列で連結する
            route('meeting-quota.checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
            // 決済画面でキャンセルした場合はダッシュボードへ戻す
            route('dashboard.index'),
        );

        Payment::create([
            'user_id' => $student->id,
            'meeting_pack_id' => $meetingPack->id,
            'stripe_checkout_session_id' => $session['id'],
            'amount' => $meetingPack->price,
            'quantity' => $meetingPack->meeting_count,
            'status' => PaymentStatus::Pending,
        ]);

        return $session['url'];
    }
}
