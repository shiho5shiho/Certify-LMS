<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingPack\MeetingPackNotDeletableException;
use App\Models\MeetingPack;
use Illuminate\Support\Facades\DB;

/**
 * 面談パックを物理削除するユースケース。公開中でなければ削除可能。
 *
 *  NOTE: 現状スキーマには購入履歴を表すテーブル(payments等)が存在せず、meeting_pack_id への外部キー参照も無いため、
 * 「公開中でなければ常に削除可」で問題ないとPM確認済み（2026-09-15）。
 * S-A-03（Stripe連携）で購入履歴テーブルが追加された際は、物理削除のままでよいか
 * （外部キー制約 or カスケード削除の扱い）を改めて設計し直す必要がある。
 *
 * @throws MeetingPackNotDeletableException 公開中の面談パックは削除不可
 */
final class DestroyAction
{
    public function __invoke(MeetingPack $meetingPack): void
    {
        if ($meetingPack->status === MeetingPackStatus::Published) {
            throw new MeetingPackNotDeletableException;
        }

        DB::transaction(fn () => $meetingPack->delete());
    }
}
