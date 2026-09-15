<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Exceptions\MeetingPack\MeetingPackInvalidTransitionException;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 面談パックを下書きへ戻す（archived → draft）ユースケース。
 *
 * @throws MeetingPackInvalidTransitionException アーカイブ以外からの呼出
 */
final class UnarchiveAction
{
    public function __invoke(MeetingPack $meetingPack, User $admin): MeetingPack
    {
        if ($meetingPack->status !== MeetingPackStatus::Archived) {
            throw MeetingPackInvalidTransitionException::forUnarchive();
        }

        return DB::transaction(function () use ($meetingPack, $admin) {
            $meetingPack->update([
                'status' => MeetingPackStatus::Draft->value,
                'updated_by_user_id' => $admin->id,
            ]);

            return $meetingPack->fresh();
        });
    }
}
