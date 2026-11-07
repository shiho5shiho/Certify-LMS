<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * 決済完了の通知を受けて、決済レコードを完了にし、購入回数分の残面談回数を加算するユースケース。
 *
 * 同じ通知が重複して届いても二重に加算しないよう、決済レコードを行ロックしてから状態を確認し、
 * 処理中のときだけ更新する。LMS で作成していない決済や、処理済みの決済の通知は何もしない。
 */
final class CompletePaymentAction
{
    public function __construct(
        private readonly GrantPurchasedQuotaAction $grantPurchasedQuota,
    ) {}

    public function __invoke(string $checkoutSessionId): void
    {
        DB::transaction(function () use ($checkoutSessionId) {
            $payment = Payment::query()
                ->where('stripe_checkout_session_id', $checkoutSessionId)
                ->lockForUpdate()
                ->first();

            if ($payment === null || $payment->status !== PaymentStatus::Pending) {
                return;
            }

            $payment->update([
                'status' => PaymentStatus::Succeeded,
                'paid_at' => now(),
            ]);

            ($this->grantPurchasedQuota)($payment);
        });
    }
}
