<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * 支払い失敗・期限切れの通知を受けて、決済レコードを失敗にするユースケース。
 *
 * 決済画面を閉じた場合も、期限切れの通知を受けた時点で失敗にする(PM 確認 Q3 / Q4)。
 * 残面談回数は変更しない。処理中以外の決済(完了済みなど)は更新しない。
 */
final class FailPaymentAction
{
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

            $payment->update(['status' => PaymentStatus::Failed]);
        });
    }
}
