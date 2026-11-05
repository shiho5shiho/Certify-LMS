<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\MeetingAlreadyStartedException;
use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Meeting\MeetingCanceledNotification;
use App\UseCases\Meeting\CancelAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CancelActionTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $this->coach = User::factory()->coach()->create();
    }

    public function test_cancels_meeting_and_refunds_quota(): void
    {
        $meeting = Meeting::factory()->reserved()->forCoach($this->coach)->forStudent($this->student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $result = app(CancelAction::class)($meeting, $this->student);

        $this->assertSame(MeetingStatus::Canceled, $result->status);
        $this->assertSame($this->student->id, $result->canceled_by_user_id);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $this->student->id,
            'related_meeting_id' => $meeting->id,
            'type' => MeetingQuotaTransactionType::Refunded->value,
            'amount' => 1,
        ]);
    }

    public function test_notifies_other_party_after_cancel(): void
    {
        $meeting = Meeting::factory()->reserved()->forCoach($this->coach)->forStudent($this->student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        app(CancelAction::class)($meeting, $this->student);

        // 操作者(受講生)本人には送らず、相手のコーチにだけ送る
        Notification::assertSentTo($this->coach, MeetingCanceledNotification::class);
        Notification::assertNotSentTo($this->student, MeetingCanceledNotification::class);
    }

    public function test_throws_when_meeting_already_started(): void
    {
        $meeting = Meeting::factory()->reserved()->forCoach($this->coach)->forStudent($this->student)->create([
            'scheduled_at' => now()->subHour()->startOfHour(),
        ]);

        $this->assertThrows(
            fn () => app(CancelAction::class)($meeting, $this->student),
            MeetingAlreadyStartedException::class,
        );

        // キャンセルされず、返却も通知もされない
        $this->assertSame(MeetingStatus::Reserved, $meeting->fresh()->status);
        $this->assertDatabaseMissing('meeting_quota_transactions', [
            'related_meeting_id' => $meeting->id,
            'type' => MeetingQuotaTransactionType::Refunded->value,
        ]);
        Notification::assertNothingSent();
    }

    public function test_throws_when_meeting_is_not_reserved(): void
    {
        $meeting = Meeting::factory()->canceled()->forCoach($this->coach)->forStudent($this->student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $this->assertThrows(
            fn () => app(CancelAction::class)($meeting, $this->student),
            MeetingStatusTransitionException::class,
        );

        $this->assertDatabaseMissing('meeting_quota_transactions', [
            'related_meeting_id' => $meeting->id,
            'type' => MeetingQuotaTransactionType::Refunded->value,
        ]);
        Notification::assertNothingSent();
    }
}
