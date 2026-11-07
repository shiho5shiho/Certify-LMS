<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * 追加面談パック購入時の面談回数付与ユースケース。
 *
 * 決済レコードの購入回数分の `purchased` トランザクションを INSERT する。
 * 決済完了 Action からは同一 DB トランザクション内で呼ばれる前提。
 */
final class GrantPurchasedQuotaAction
{
    public function __invoke(Payment $payment): MeetingQuotaTransaction
    {
        return DB::transaction(fn () => MeetingQuotaTransaction::create([
            'user_id' => $payment->user_id,
            'type' => MeetingQuotaTransactionType::Purchased,
            'amount' => $payment->quantity,
            'related_payment_id' => $payment->id,
            'occurred_at' => now(),
        ]));
    }
}
