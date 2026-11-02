<?php

declare(strict_types=1);

namespace App\UseCases\GoogleCalendar;

use App\Exceptions\Mentoring\GoogleCalendarCredentialRevokedException;
use App\Exceptions\Mentoring\GoogleCalendarRequestFailedException;
use App\Models\Meeting;
use App\Services\Contracts\GoogleCalendarServiceContract;
use Illuminate\Support\Facades\Log;

/**
 * 予約が成立した面談を、担当コーチの Google カレンダーに予定として登録するユースケース。
 *
 * コーチが未連携なら何もしない。登録に失敗しても予約は成立済みのまま(例外は投げずログに記録する)。
 * 登録できた場合はイベント ID を面談に保存し、キャンセル時の削除に使う。
 */
final class RegisterMeetingEventAction
{
    public function __construct(
        private readonly GoogleCalendarServiceContract $googleCalendar,
    ) {}

    public function __invoke(Meeting $meeting): void
    {
        $credential = $meeting->coach->googleCredential;
        if ($credential === null) {
            return;
        }

        try {
            $eventId = $this->googleCalendar->createEvent($credential, $meeting);
        } catch (GoogleCalendarCredentialRevokedException) {
            // 連携は削除済みで、コーチは未連携に戻っている。未連携コーチと同じく登録しない
            return;
        } catch (GoogleCalendarRequestFailedException $e) {
            Log::warning('Google カレンダーへの面談予定の登録に失敗しました。', [
                'meeting_id' => $meeting->id,
                'coach_id' => $meeting->coach_id,
                'exception' => $e,
            ]);

            return;
        }

        $meeting->update(['google_event_id' => $eventId]);
    }
}
