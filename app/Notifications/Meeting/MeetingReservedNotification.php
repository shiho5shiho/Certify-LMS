<?php

declare(strict_types=1);

namespace App\Notifications\Meeting;

use App\Models\Meeting;
use App\Notifications\BusinessEventNotification;

/**
 * 面談の予約通知。担当に決まったコーチに送る(予約した受講生本人には送らない)。
 */
final class MeetingReservedNotification extends BusinessEventNotification
{
    public function __construct(private readonly Meeting $meeting) {}

    protected function notificationType(): string
    {
        return 'meeting_reserved';
    }

    protected function title(): string
    {
        return "{$this->meeting->student->name}さんが面談を予約しました";
    }

    protected function message(): string
    {
        return $this->meeting->scheduled_at->format('Y/m/d H:i')
            ."（{$this->meeting->enrollment->certification->name}）";
    }

    protected function url(): string
    {
        return route('meetings.show', $this->meeting);
    }
}
