<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Exceptions\MeetingQuota\InsufficientMeetingQuotaException;
use App\Exceptions\Mentoring\MeetingNoAvailableCoachException;
use App\Exceptions\Mentoring\MeetingOutOfAvailabilityException;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Meeting\MeetingReservedNotification;
use App\UseCases\Meeting\StoreAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;

    private Enrollment $enrollment;

    private Carbon $scheduledAt;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        // 月曜 09:00〜18:00 に枠を持つ担当コーチ 1 名と、その資格を受講中の受講生
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $this->coach = User::factory()->coach()->inProgress()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);
        $certification = Certification::factory()->published()->create();
        $certification->coaches()->attach($this->coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
        CoachAvailability::factory()->forCoach($this->coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        $this->enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $this->scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);
    }

    public function test_creates_reserved_meeting_and_consumes_quota(): void
    {
        $meeting = app(StoreAction::class)($this->enrollment, $this->scheduledAt, '相談したい');

        $this->assertSame(MeetingStatus::Reserved, $meeting->status);
        $this->assertSame($this->coach->id, $meeting->coach_id);
        $this->assertNotNull($meeting->meeting_quota_transaction_id);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $this->enrollment->user_id,
            'related_meeting_id' => $meeting->id,
            'type' => MeetingQuotaTransactionType::Consumed->value,
        ]);
    }

    public function test_notifies_coach_after_reservation(): void
    {
        app(StoreAction::class)($this->enrollment, $this->scheduledAt, '相談したい');

        Notification::assertSentTo($this->coach, MeetingReservedNotification::class);
    }

    public function test_throws_when_quota_is_insufficient(): void
    {
        $this->enrollment->user->forceFill(['max_meetings' => 0])->save();

        $this->assertThrows(
            fn () => app(StoreAction::class)($this->enrollment, $this->scheduledAt, '相談したい'),
            InsufficientMeetingQuotaException::class,
        );

        // 予約は作られず、通知も送られない
        $this->assertDatabaseCount('meetings', 0);
        Notification::assertNothingSent();
    }

    public function test_throws_when_slot_has_no_available_coach(): void
    {
        // 同コーチ・同時刻に予約済の面談があると、空き枠の検証で弾かれる
        Meeting::factory()->reserved()->forCoach($this->coach)->create(['scheduled_at' => $this->scheduledAt]);

        $this->assertThrows(
            fn () => app(StoreAction::class)($this->enrollment, $this->scheduledAt, '相談したい'),
            MeetingOutOfAvailabilityException::class,
        );

        Notification::assertNothingSent();
    }

    public function test_throws_when_same_slot_is_taken_concurrently(): void
    {
        // canceled は空き枠判定をすり抜けるが、(coach_id, scheduled_at) UNIQUE には引っかかるため、
        // 同時刻に先行予約された race condition を再現できる
        Meeting::factory()->canceled()->forCoach($this->coach)->create(['scheduled_at' => $this->scheduledAt]);

        $this->assertThrows(
            fn () => app(StoreAction::class)($this->enrollment, $this->scheduledAt, '相談したい'),
            MeetingNoAvailableCoachException::class,
        );

        $this->assertSame(0, Meeting::query()->where('status', MeetingStatus::Reserved->value)->count());
        Notification::assertNothingSent();
    }
}
