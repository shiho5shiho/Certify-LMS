<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Meeting\MeetingReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendMeetingRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-24 09:00:00'));
    }

    public function test_eve_sends_to_student_and_coach_of_tomorrow_reserved_meetings_only(): void
    {
        $tomorrowFirst = $this->makeMeeting('2026-10-25 00:00:00');
        $tomorrowLast = $this->makeMeeting('2026-10-25 23:00:00');
        $today = $this->makeMeeting('2026-10-24 15:00:00');
        $dayAfterTomorrow = $this->makeMeeting('2026-10-26 09:00:00');
        $canceled = $this->makeMeeting('2026-10-25 10:00:00', 'canceled');

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'eve'])
            ->assertExitCode(Command::SUCCESS);

        foreach ([$tomorrowFirst, $tomorrowLast] as $meeting) {
            Notification::assertSentTo($meeting->student, MeetingReminderNotification::class);
            Notification::assertSentTo($meeting->coach, MeetingReminderNotification::class);
            $this->assertNotNull($meeting->fresh()->eve_reminded_at);
        }
        foreach ([$today, $dayAfterTomorrow, $canceled] as $meeting) {
            Notification::assertNotSentTo($meeting->student, MeetingReminderNotification::class);
            Notification::assertNotSentTo($meeting->coach, MeetingReminderNotification::class);
        }
    }

    public function test_one_hour_before_sends_to_meetings_starting_within_60_minutes_only(): void
    {
        $in30Minutes = $this->makeMeeting('2026-10-24 09:30:00');
        $in60Minutes = $this->makeMeeting('2026-10-24 10:00:00');
        $in90Minutes = $this->makeMeeting('2026-10-24 10:30:00');
        $alreadyStarted = $this->makeMeeting('2026-10-24 08:30:00');

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'one_hour_before'])
            ->assertExitCode(Command::SUCCESS);

        foreach ([$in30Minutes, $in60Minutes] as $meeting) {
            Notification::assertSentTo($meeting->student, MeetingReminderNotification::class);
            Notification::assertSentTo($meeting->coach, MeetingReminderNotification::class);
            $this->assertNotNull($meeting->fresh()->one_hour_reminded_at);
        }
        foreach ([$in90Minutes, $alreadyStarted] as $meeting) {
            Notification::assertNotSentTo($meeting->student, MeetingReminderNotification::class);
        }
    }

    public function test_does_not_send_twice_when_run_again(): void
    {
        $meeting = $this->makeMeeting('2026-10-25 10:00:00');

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'eve']);
        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'eve']);

        Notification::assertSentToTimes($meeting->student, MeetingReminderNotification::class, 1);
        Notification::assertSentToTimes($meeting->coach, MeetingReminderNotification::class, 1);
    }

    public function test_does_not_send_to_student_who_is_not_in_progress(): void
    {
        $meeting = $this->makeMeeting('2026-10-25 10:00:00');
        $meeting->student->update(['status' => 'graduated']);

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'eve']);

        Notification::assertNotSentTo($meeting->student, MeetingReminderNotification::class);
        Notification::assertSentTo($meeting->coach, MeetingReminderNotification::class);
    }

    public function test_invalid_window_fails(): void
    {
        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'foo'])
            ->expectsOutput('--window には eve または one_hour_before を指定してください。')
            ->assertExitCode(Command::INVALID);

        Notification::assertNothingSent();
    }

    private function makeMeeting(string $scheduledAt, string $state = 'reserved'): Meeting
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();

        return Meeting::factory()->{$state}()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => Carbon::parse($scheduledAt),
        ]);
    }
}
