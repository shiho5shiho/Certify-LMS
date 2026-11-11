<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingPack\MeetingPackHasPaymentsException;
use App\Exceptions\MeetingPack\MeetingPackNotDeletableException;
use App\Models\MeetingPack;
use Illuminate\Support\Facades\DB;

/**
 * 面談パックを物理削除するユースケース。公開中でなく、購入記録がなければ削除可能。
 *
 * 購入記録(payments)がある面談パックは、決済ステータスにかかわらず削除不可。
 * 削除すると、どのパックが購入されたかが分からなくなり、過去の購入を監査できなくなるため。
 * 販売を終了する場合はアーカイブで対応する。
 *
 * @throws MeetingPackNotDeletableException 公開中の面談パックは削除不可
 * @throws MeetingPackHasPaymentsException 購入記録がある面談パックは削除不可
 */
final class DestroyAction
{
    public function __invoke(MeetingPack $meetingPack): void
    {
        if ($meetingPack->status === MeetingPackStatus::Published) {
            throw new MeetingPackNotDeletableException;
        }

        if ($meetingPack->payments()->exists()) {
            throw new MeetingPackHasPaymentsException;
        }

        DB::transaction(fn () => $meetingPack->delete());
    }
}
