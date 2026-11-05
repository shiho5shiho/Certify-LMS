<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\MeetingQuota\InsufficientMeetingQuotaException;
use App\Exceptions\Mentoring\MeetingNoAvailableCoachException;
use App\Exceptions\Mentoring\MeetingOutOfAvailabilityException;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Meeting\MeetingReservedNotification;
use App\Services\CoachMeetingLoadService;
use App\Services\MeetingAvailabilityService;
use App\Services\MeetingQuotaService;
use App\Services\NotificationDispatchService;
use App\UseCases\GoogleCalendar\RegisterMeetingEventAction;
use App\UseCases\MeetingQuota\ConsumeQuotaAction;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 受講生による面談予約。残面談回数を確認し、空き枠から過去実績最少のコーチを自動割当して reserved で確定する。
 * 同時刻 race condition は (coach_id, scheduled_at) UNIQUE 違反として検知し 409 へ変換する。
 *
 * 予約通知と Google カレンダーへの予定登録は DB::afterCommit() でコミット後に実行し、
 * 失敗しても予約の成立に影響させない。
 */
final class StoreAction
{
    public function __construct(
        private readonly MeetingAvailabilityService $availabilityService,
        private readonly CoachMeetingLoadService $coachLoadService,
        private readonly MeetingQuotaService $quotaService,
        private readonly ConsumeQuotaAction $consumeQuota,
        private readonly NotificationDispatchService $notifier,
        private readonly RegisterMeetingEventAction $registerEvent,
    ) {}

    /**
     * @throws InsufficientMeetingQuotaException 残面談回数が 0
     * @throws MeetingOutOfAvailabilityException 予約できない時間帯
     * @throws MeetingNoAvailableCoachException 空いているコーチがいない / 同時刻に先行予約された
     */
    public function __invoke(Enrollment $enrollment, Carbon $scheduledAt, string $topic): Meeting
    {
        $student = $enrollment->user;

        return DB::transaction(function () use ($enrollment, $student, $scheduledAt, $topic) {
            if ($this->quotaService->remaining($student) < 1) {
                throw new InsufficientMeetingQuotaException;
            }

            $this->availabilityService->validateSlot($enrollment->certification, $scheduledAt);

            $candidates = $this->findAvailableCoaches($enrollment->certification, $scheduledAt);
            if ($candidates->isEmpty()) {
                throw new MeetingNoAvailableCoachException;
            }

            $coach = $this->coachLoadService->leastLoadedCoach($candidates);

            try {
                $meeting = Meeting::create([
                    'enrollment_id' => $enrollment->id,
                    'coach_id' => $coach->id,
                    'student_id' => $student->id,
                    'scheduled_at' => $scheduledAt,
                    'status' => MeetingStatus::Reserved->value,
                    'topic' => $topic,
                    'meeting_url_snapshot' => $coach->meeting_url,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                // 同時刻に他受講生が先行予約した race condition: UNIQUE(coach_id, scheduled_at) で弾かれた
                throw new MeetingNoAvailableCoachException($e);
            }

            $transaction = ($this->consumeQuota)($student, $meeting->id);
            $meeting->update(['meeting_quota_transaction_id' => $transaction->id]);
            $meeting = $meeting->fresh();

            // DB の確定後に実行する(通知・登録に失敗しても予約は成立させる)
            DB::afterCommit(function () use ($meeting, $student): void {
                $meeting->load(['coach.googleCredential', 'student', 'enrollment.certification']);

                $this->notifier->send([$meeting->coach], $student, new MeetingReservedNotification($meeting));
                ($this->registerEvent)($meeting);
            });

            return $meeting;
        });
    }

    /**
     * 担当コーチ集合のうち、(1) 当該時刻に有効な availability 枠があり、
     * (2) 当該時刻に reserved / completed の Meeting を持たないコーチ集合を返す。
     * (3) Google カレンダー連携済みなら当該時刻に Google 上の予定がないコーチ集合を返す。
     *
     * @return Collection<int, User>
     */
    private function findAvailableCoaches(Certification $certification, Carbon $scheduledAt): Collection
    {
        $time = $scheduledAt->format('H:i:s');

        return $certification->coaches()
            ->whereHas('coachAvailabilities', function ($q) use ($scheduledAt, $time) {
                $q->where('day_of_week', $scheduledAt->dayOfWeek)
                    ->where('is_active', true)
                    ->where('start_time', '<=', $time)
                    ->where('end_time', '>', $time);
            })
            ->whereDoesntHave('meetingsAsCoach', function ($q) use ($scheduledAt) {
                $q->where('scheduled_at', $scheduledAt)
                    ->whereIn('status', [MeetingStatus::Reserved->value, MeetingStatus::Completed->value]);
            })
            ->with('googleCredential')
            ->get()
            ->reject(fn (User $coach) => $this->availabilityService->isBusyOnGoogleCalendar($coach, $scheduledAt->copy()))
            ->values();
    }
}
