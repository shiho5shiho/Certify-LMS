<?php

declare(strict_types=1);

namespace App\Notifications\Meeting;

use App\Enums\MeetingReminderWindow;
use App\Models\Meeting;
use App\Notifications\BusinessEventNotification;

/**
 * 面談のリマインダー通知(前日 / 1 時間前)。面談の受講生と担当コーチに送る。
 */
final class MeetingReminderNotification extends BusinessEventNotification
{
    public function __construct(
        private readonly Meeting $meeting,
        private readonly MeetingReminderWindow $window,
    ) {}

    protected function notificationType(): string
    {
        return 'meeting_reminder';
    }

    protected function title(): string
    {
        return $this->window->title();
    }

    protected function message(): string
    {
        $start = $this->meeting->scheduled_at;

        // 面談は 60 分固定のため、終了時刻は開始 + 60 分
        return $start->format('Y/m/d H:i')
            .'〜'.$start->copy()->addMinutes(60)->format('H:i')
            ."（{$this->meeting->enrollment->certification->name}）";
    }

    protected function url(): string
    {
        return route('meetings.show', $this->meeting);
    }
}
