<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Console\Commands\Mentoring\SendMeetingRemindersCommand;
use App\Enums\MeetingReminderWindow;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Notifications\Meeting\MeetingReminderNotification;
use App\Services\NotificationDispatchService;
use Illuminate\Support\Facades\DB;

/**
 * 1 件の面談に、指定タイミングのリマインダーを送るユースケース。
 *
 * コマンドが重複起動しても二重に届かないよう、`lockForUpdate()` で対象行を取得し直し、
 * 予約済 かつ 未送信 であることを再確認してから送信し、送信日時を記録する(冪等)。
 * 送信に失敗した場合はトランザクションごと戻り未送信のままになるため、次回の定期実行で再送される。
 *
 * 宛先は面談の受講生と担当コーチ。担当解除後でも、その面談のコーチには送る(PM 確認済み)。
 *
 * @see SendMeetingRemindersCommand
 */
final class SendMeetingReminderAction
{
    public function __construct(
        private readonly NotificationDispatchService $notifier,
    ) {}

    /**
     * @return bool 送信した場合 true
     */
    public function __invoke(Meeting $meeting, MeetingReminderWindow $window): bool
    {
        return DB::transaction(function () use ($meeting, $window) {
            $column = $window->remindedAtColumn();

            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->first();
            if ($locked === null
                || $locked->status !== MeetingStatus::Reserved
                || $locked->{$column} !== null
            ) {
                return false;
            }

            $locked->load(['coach', 'student', 'enrollment.certification']);

            // 定期実行のため操作した本人はいない(actor = null)
            $this->notifier->send(
                [$locked->student, $locked->coach],
                null,
                new MeetingReminderNotification($locked, $window),
            );

            $locked->update([$column => now()]);

            return true;
        });
    }
}
