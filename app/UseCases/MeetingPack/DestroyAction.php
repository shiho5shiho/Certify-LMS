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
 * NOTE: 「購入履歴があっても公開中でなければ削除可としてよいか」はPM確認中（回答待ち）。
 * NGの回答が来た場合、ここに購入履歴チェックを追加する（Payment実装後）。
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

        DB::transaction(fn() => $meetingPack->delete());
    }
}
