<?php

declare(strict_types=1);

namespace App\UseCases\GoogleCalendar;

use App\Exceptions\Mentoring\GoogleCalendarCredentialRevokedException;
use App\Exceptions\Mentoring\GoogleCalendarRequestFailedException;
use App\Models\Meeting;
use App\Services\Contracts\GoogleCalendarServiceContract;
use Illuminate\Support\Facades\Log;

/**
 * キャンセルされた面談の予定を、担当コーチの Google カレンダーから削除するユースケース。
 *
 * 予定を登録していない面談(未連携時の予約・登録失敗)や、コーチが連携を解除済みの場合は何もしない。
 * 削除に失敗してもキャンセルは成立済みのまま(例外は投げずログに記録する)。
 */
final class DeleteMeetingEventAction
{
    public function __construct(
        private readonly GoogleCalendarServiceContract $googleCalendar,
    ) {}

    public function __invoke(Meeting $meeting): void
    {
        $credential = $meeting->coach->googleCredential;
        if ($meeting->google_event_id === null || $credential === null) {
            return;
        }

        try {
            $this->googleCalendar->deleteEvent($credential, $meeting->google_event_id);
        } catch (GoogleCalendarCredentialRevokedException) {
            // 連携は削除済み。Google 側の予定を操作できないため何もしない
            return;
        } catch (GoogleCalendarRequestFailedException $e) {
            Log::warning('Google カレンダーの面談予定の削除に失敗しました。', [
                'meeting_id' => $meeting->id,
                'coach_id' => $meeting->coach_id,
                'google_event_id' => $meeting->google_event_id,
                'exception' => $e,
            ]);
        }
    }
}
