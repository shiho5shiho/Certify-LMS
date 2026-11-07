<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Models\Payment;
use App\Models\User;

/**
 * 決済完了画面に表示する決済レコードを取得するユースケース。
 *
 * Stripe から戻ってきたときの session_id で、ログイン中の受講生本人の決済レコードのみ取得する。
 * 該当がない(session_id なし / 他人の決済)場合は null を返し、画面では購入内容を表示しない。
 * ステータスの更新は Webhook で行うため、ここでは Stripe に問い合わせない。
 */
final class ShowCheckoutSuccessAction
{
    public function __invoke(User $student, ?string $sessionId): ?Payment
    {
        if ($sessionId === null || $sessionId === '') {
            return null;
        }

        return Payment::query()
            ->where('user_id', $student->id)
            ->where('stripe_checkout_session_id', $sessionId)
            ->with('meetingPack')
            ->first();
    }
}
