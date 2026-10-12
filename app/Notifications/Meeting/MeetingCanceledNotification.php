<?php

declare(strict_types=1);

namespace App\Notifications\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\Notifications\BusinessEventNotification;

/**
 * 面談のキャンセル通知。キャンセルしていない側(受講生 or コーチ)に送る。
 */
final class MeetingCanceledNotification extends BusinessEventNotification
{
    public function __construct(
        private readonly Meeting $meeting,
        private readonly User $canceledBy,
    ) {}

    protected function notificationType(): string
    {
        return 'meeting_canceled';
    }

    protected function title(): string
    {
        return "{$this->canceledBy->name}さんが面談をキャンセルしました";
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
