<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Meeting\MeetingCanceledNotification;
use App\Services\NotificationDispatchService;
use App\UseCases\GoogleCalendar\DeleteMeetingEventAction;
use App\UseCases\MeetingQuota\RefundQuotaAction;
use Illuminate\Support\Facades\DB;

/**
 * 当事者(受講生 or コーチ)による面談キャンセル。
 * reserved かつ開始前のみキャンセル可。消費済の面談回数 1 回分を返却する。
 *
 * Google カレンダーの予定削除とキャンセル通知は DB::afterCommit() でコミット後に実行し、
 * 失敗してもキャンセルの成立に影響させない。
 */
final class CancelAction
{
    public function __construct(
        private readonly RefundQuotaAction $refundQuota,
        private readonly NotificationDispatchService $notifier,
        private readonly DeleteMeetingEventAction $deleteEvent,
    ) {}

    /**
     * @throws MeetingStatusTransitionException reserved 以外の面談
     * @throws MeetingAlreadyStartedException 開始時刻を過ぎた面談
     */
    public function __invoke(Meeting $meeting, User $actor): Meeting
    {
        return DB::transaction(function () use ($meeting, $actor) {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->first();
            if ($locked === null || $locked->status !== MeetingStatus::Reserved) {
                throw MeetingStatusTransitionException::forCancel();
            }

            if ($locked->scheduled_at->lessThanOrEqualTo(now())) {
                throw new MeetingAlreadyStartedException;
            }

            $locked->update([
                'status' => MeetingStatus::Canceled->value,
                'canceled_by_user_id' => $actor->id,
                'canceled_at' => now(),
            ]);
            // 消費済の面談回数 1 回分を、操作者ではなく面談の受講生に返却する
            ($this->refundQuota)($locked->student, $locked->id);

            // DB の確定後に実行する(削除・通知に失敗してもキャンセルは成立させる)
            DB::afterCommit(function () use ($meeting, $actor): void {
                $meeting->refresh()->load(['coach.googleCredential', 'student', 'enrollment.certification']);

                ($this->deleteEvent)($meeting);
                // 面談キャンセルは例外として、担当解除後でもその面談のコーチに通知する(PM 確認済み)
                $this->notifier->send(
                    [$meeting->coach, $meeting->student],
                    $actor,
                    new MeetingCanceledNotification($meeting, $actor),
                );
            });

            return $meeting;
        });
    }
}
