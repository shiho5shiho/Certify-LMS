<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\GoogleCalendarCredentialRevokedException;
use App\Exceptions\Mentoring\GoogleCalendarRequestFailedException;
use App\Exceptions\Mentoring\MeetingOutOfAvailabilityException;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Contracts\GoogleCalendarServiceContract;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * 担当コーチ集合の面談可能時間枠を 60 分単位で展開し、空きスロットを集計する Service。
 *
 * 受講生の予約画面が「該当資格の担当コーチ全員の有効枠 Union」を 1 日単位で取得し、
 * 既存予約済時刻 を除外して各スロットの「予約可能なコーチ数」を返す。受講生にコーチ個別は提示せず、
 * 予約確定時にコーチを自動割当する。
 * Google カレンダー連携済みのコーチは、Google 上の「予定あり」と重なる枠を空きから除外する。
 */
final class MeetingAvailabilityService
{
    /**
     * 「コーチ ID @ 日付」ごとの Google の予定あり時間。null は取得失敗(その日は予約不可)。
     * 同じリクエスト内で何度も Google に問い合わせないためのキャッシュ。
     *
     * @var array<string, list<array{start: Carbon, end: Carbon}>|null>
     */
    private array $busyCache = [];

    public function __construct(
        private readonly GoogleCalendarServiceContract $googleCalendar,
    ) {}

    /**
     * 指定 Certification の担当コーチ集合について、指定日 1 日分の 60 分単位空きスロットを返す。
     *
     * 1 リクエストあたり availability 1 クエリ + meetings 1 クエリ で完結させる。
     * Google カレンダー連携済みのコーチは、Google 上の「予定あり」と重なるスロットを除外する。
     *
     * @return Collection<int, array{slot_start: Carbon, slot_end: Carbon, available_coach_count: int}>
     */
    public function slotsForCertification(Certification $certification, Carbon $date): Collection
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();
        $dayOfWeek = $date->dayOfWeek;

        $coaches = $certification->coaches()->with('googleCredential')->get();
        if ($coaches->isEmpty()) {
            return collect();
        }

        $coachIds = $coaches->pluck('id')->all();
        $coachesById = $coaches->keyBy('id');

        $availabilities = CoachAvailability::query()
            ->whereIn('coach_id', $coachIds)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get();

        $existingMeetings = Meeting::query()
            ->whereIn('coach_id', $coachIds)
            ->whereBetween('scheduled_at', [$dayStart, $dayEnd])
            ->whereIn('status', [MeetingStatus::Reserved->value, MeetingStatus::Completed->value])
            ->get(['coach_id', 'scheduled_at']);

        // 予約済スロットを (coach_id => Set<H:i>) で索引化
        $bookedByCoach = $existingMeetings
            ->groupBy('coach_id')
            ->map(fn ($rows) => $rows->map(fn (Meeting $m) => $m->scheduled_at->format('H:i'))->all());

        /** @var array<string, int> $slotCounts スロット開始時刻(H:i) → available coach 数 */
        $slotCounts = [];

        foreach ($availabilities as $availability) {
            $slot = Carbon::parse($date->format('Y-m-d').' '.$availability->start_time);
            $end = Carbon::parse($date->format('Y-m-d').' '.$availability->end_time);

            while ($slot->copy()->addHour() <= $end) {
                $slotKey = $slot->format('H:i');
                $coachId = $availability->coach_id;
                $booked = $bookedByCoach[$coachId] ?? [];

                if (! in_array($slotKey, $booked, true)
                    && ! $this->isBusyOnGoogleCalendar($coachesById[$coachId], $slot->copy())) {
                    $slotCounts[$slotKey] = ($slotCounts[$slotKey] ?? 0) + 1;
                }

                $slot->addHour();
            }
        }

        ksort($slotCounts);

        return collect($slotCounts)->map(function (int $count, string $time) use ($date) {
            $start = Carbon::parse($date->format('Y-m-d').' '.$time);

            return [
                'slot_start' => $start,
                'slot_end' => $start->copy()->addHour(),
                'available_coach_count' => $count,
            ];
        })->values();
    }

    /**
     * 指定 scheduled_at が certification 担当コーチ集合の有効枠内かを検証する。
     * 枠外なら MeetingOutOfAvailabilityException を throw する。
     *
     * @throws MeetingOutOfAvailabilityException
     */
    public function validateSlot(Certification $certification, Carbon $scheduledAt): void
    {
        $slots = $this->slotsForCertification($certification, $scheduledAt->copy()->startOfDay());

        $matched = $slots->contains(
            fn (array $slot) => $slot['slot_start']->equalTo($scheduledAt) && $slot['available_coach_count'] > 0,
        );

        if (! $matched) {
            throw new MeetingOutOfAvailabilityException;
        }
    }

    /**
     * 連携済みコーチが、指定の 1 時間枠に Google カレンダーで予定ありかを判定する。
     *
     * 未連携のコーチは常に false(従来どおりの判定)。予定の取得に失敗した日は、その日の枠をすべて予約不可(true)とする。
     * 予約確定時のコーチ割当(MeetingController::findAvailableCoaches)からも同じ判定で呼ばれる。
     */
    public function isBusyOnGoogleCalendar(User $coach, Carbon $slotStart): bool
    {
        if ($coach->googleCredential === null) {
            return false;
        }

        $busyIntervals = $this->googleBusyIntervals($coach, $slotStart);

        // 取得失敗を「予定なし」と扱うとダブルブッキングにつながるため、予約不可とする(PM 確認 Q1)
        if ($busyIntervals === null) {
            return true;
        }

        $slotEnd = $slotStart->copy()->addHour();

        foreach ($busyIntervals as $interval) {
            if ($interval['start']->lessThan($slotEnd) && $interval['end']->greaterThan($slotStart)) {
                return true;
            }
        }

        return false;
    }

    /**
     * コーチの指定日 1 日分の予定あり時間を返す(コーチ × 日付ごとに 1 回だけ Google に問い合わせる)。
     *
     * @return list<array{start: Carbon, end: Carbon}>|null 取得に失敗した場合は null
     */
    private function googleBusyIntervals(User $coach, Carbon $date): ?array
    {
        $cacheKey = $coach->id.'@'.$date->toDateString();

        if (array_key_exists($cacheKey, $this->busyCache)) {
            return $this->busyCache[$cacheKey];
        }

        try {
            $busyIntervals = $this->googleCalendar->busyIntervals(
                $coach->googleCredential,
                $date->copy()->startOfDay(),
                $date->copy()->addDay()->startOfDay(),
            );
        } catch (GoogleCalendarCredentialRevokedException) {
            // 連携は削除済み。未連携のコーチとして従来どおりの判定にする(PM 確認 Q3)
            $busyIntervals = [];
        } catch (GoogleCalendarRequestFailedException $e) {
            Log::warning('Google カレンダーの予定あり時間を取得できませんでした。', [
                'coach_id' => $coach->id,
                'date' => $date->toDateString(),
                'exception' => $e,
            ]);
            $busyIntervals = null;
        }

        return $this->busyCache[$cacheKey] = $busyIntervals;
    }
}
