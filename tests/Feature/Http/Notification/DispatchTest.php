<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Enums\MeetingStatus;
use App\Models\Certification;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\Chat\ChatMessageReceivedNotification;
use App\Notifications\Meeting\MeetingCanceledNotification;
use App\Notifications\Meeting\MeetingReservedNotification;
use App\Notifications\QaBoard\QaReplyReceivedNotification;
use App\Notifications\QaBoard\QaThreadPostedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 業務イベント(チャット / 質問掲示板 / 面談)から、相手方に通知が送られることを HTTP 経由で検証する。
 * 操作した本人には送られないこと、文言と移動先が承認済みの仕様どおりであることも確認する。
 */
class DispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function attachCoach(Certification $certification, User $coach, ?Carbon $unassignedAt = null): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'unassigned_at' => $unassignedAt,
        ]);
    }

    public function test_chat_message_notifies_other_room_members(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach);
        $enrollment = Enrollment::factory()->for($student)->for($certification)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $student->id]);
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $coach->id]);

        $this->actingAs($student)->post(route('chat.storeMessage', $room), ['body' => '相談したいです。']);

        Notification::assertSentTo(
            $coach,
            ChatMessageReceivedNotification::class,
            fn ($notification) => $notification->toArray($coach) === [
                'notification_type' => 'chat_message_received',
                'title' => "{$student->name}さんからメッセージが届きました",
                'message' => '相談したいです。',
                'url' => route('chat.show', $room),
            ],
        );
        Notification::assertNotSentTo($student, ChatMessageReceivedNotification::class);
    }

    public function test_thread_post_notifies_only_assigned_coaches(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $assignedCoach = User::factory()->coach()->inProgress()->create();
        $unassignedCoach = User::factory()->coach()->inProgress()->create();
        $otherCoach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $assignedCoach);
        $this->attachCoach($certification, $unassignedCoach, unassignedAt: now()->subDay());

        $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => '比較回数の数え方',
            'body' => '教えてください。',
        ]);

        $thread = QaThread::query()->firstOrFail();
        Notification::assertSentTo(
            $assignedCoach,
            QaThreadPostedNotification::class,
            fn ($notification) => $notification->toArray($assignedCoach) === [
                'notification_type' => 'qa_thread_posted',
                'title' => "{$student->name}さんが質問を投稿しました",
                'message' => '比較回数の数え方',
                'url' => route('qa-board.show', $thread),
            ],
        );
        Notification::assertNotSentTo($unassignedCoach, QaThreadPostedNotification::class);
        Notification::assertNotSentTo($otherCoach, QaThreadPostedNotification::class);
        Notification::assertNotSentTo($student, QaThreadPostedNotification::class);
    }

    public function test_reply_notifies_thread_author(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $replier = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create([
            'certification_id' => $certification->id,
            'user_id' => $author->id,
            'title' => '比較回数の数え方',
        ]);

        $this->actingAs($replier)->post(route('qa-board.replies.store', $thread), ['body' => '根から数えます。']);

        $reply = $thread->replies()->firstOrFail();
        Notification::assertSentTo(
            $author,
            QaReplyReceivedNotification::class,
            fn ($notification) => $notification->toArray($author) === [
                'notification_type' => 'qa_reply_received',
                'title' => "{$replier->name}さんがあなたの質問に回答しました",
                'message' => '比較回数の数え方',
                'url' => route('qa-board.show', $thread).'#reply-'.$reply->id,
            ],
        );
        Notification::assertNotSentTo($replier, QaReplyReceivedNotification::class);
    }

    public function test_reply_to_own_thread_sends_no_notification(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->create([
            'certification_id' => $certification->id,
            'user_id' => $author->id,
        ]);

        $this->actingAs($author)->post(route('qa-board.replies.store', $thread), ['body' => '自己解決しました。']);

        Notification::assertNothingSent();
    }

    public function test_meeting_reservation_notifies_assigned_coach_only(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        $this->actingAs($student)->post(route('meetings.store', $enrollment), [
            'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
            'topic' => '相談したい',
        ]);

        $meeting = Meeting::query()->firstOrFail();
        Notification::assertSentTo(
            $coach,
            MeetingReservedNotification::class,
            fn ($notification) => $notification->toArray($coach) === [
                'notification_type' => 'meeting_reserved',
                'title' => "{$student->name}さんが面談を予約しました",
                'message' => $scheduledAt->format('Y/m/d H:i')."（{$certification->name}）",
                'url' => route('meetings.show', $meeting),
            ],
        );
        Notification::assertNotSentTo($student, MeetingReservedNotification::class);
    }

    public function test_meeting_cancel_by_student_notifies_coach(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create();

        $this->actingAs($student)->post(route('meetings.cancel', $meeting));

        Notification::assertSentTo(
            $coach,
            MeetingCanceledNotification::class,
            fn ($notification) => $notification->toArray($coach)['title'] === "{$student->name}さんが面談をキャンセルしました",
        );
        Notification::assertNotSentTo($student, MeetingCanceledNotification::class);
        $this->assertSame(MeetingStatus::Canceled, $meeting->fresh()->status);
    }

    public function test_meeting_cancel_by_coach_notifies_student(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create();

        $this->actingAs($coach)->post(route('meetings.cancel', $meeting));

        Notification::assertSentTo(
            $student,
            MeetingCanceledNotification::class,
            fn ($notification) => $notification->toArray($student)['title'] === "{$coach->name}さんが面談をキャンセルしました",
        );
        Notification::assertNotSentTo($coach, MeetingCanceledNotification::class);
    }

    public function test_chat_message_does_not_notify_unassigned_coach(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $assignedCoach = User::factory()->coach()->inProgress()->create();
        $unassignedCoach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $assignedCoach);
        $this->attachCoach($certification, $unassignedCoach, unassignedAt: now()->subDay());
        $enrollment = Enrollment::factory()->for($student)->for($certification)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        foreach ([$student, $assignedCoach, $unassignedCoach] as $member) {
            ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $member->id]);
        }

        $this->actingAs($student)->post(route('chat.storeMessage', $room), ['body' => '相談したいです。']);

        Notification::assertSentTo($assignedCoach, ChatMessageReceivedNotification::class);
        Notification::assertNotSentTo($unassignedCoach, ChatMessageReceivedNotification::class);
    }

    public function test_meeting_cancel_notifies_coach_even_after_unassigned(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach, unassignedAt: now()->subDay());
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->forEnrollment($enrollment)->create();

        $this->actingAs($student)->post(route('meetings.cancel', $meeting));

        Notification::assertSentTo($coach, MeetingCanceledNotification::class);
    }
}
