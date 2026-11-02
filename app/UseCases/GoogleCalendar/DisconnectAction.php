<?php

declare(strict_types=1);

namespace App\UseCases\GoogleCalendar;

use App\Models\User;

/**
 * コーチ本人の Google カレンダー連携を解除するユースケース。
 *
 * LMS 側の連携情報を削除する。Google 側に登録済みの予定は削除しない(面談設定タブの説明文どおり)。
 * 未連携の状態で呼ばれても何もしない。
 */
final class DisconnectAction
{
    public function __invoke(User $coach): void
    {
        $coach->googleCredential()->delete();
    }
}
