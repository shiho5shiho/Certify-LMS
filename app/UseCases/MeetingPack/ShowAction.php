<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;

/**
 * admin用の面談パック詳細を取得するユースケース。作成者/更新者をEager Loadingで揃える。
 * 購入履歴（payments）はPaymentモデル未実装（S-A-03未着手）のため本Actionでは扱わない。
 * Bladeは `class_exists(\App\Models\Payment::class)` で存在チェック済みなので、
 * Payment実装後にここへ `payments` のEager Loadingを追加すればよい。
 */
final class ShowAction
{
    public function __invoke(MeetingPack $meetingPack): MeetingPack
    {
        return $meetingPack->load(['createdBy', 'updatedBy']);
    }
}
