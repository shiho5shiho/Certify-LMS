<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications\Meeting;

use App\Enums\MeetingReminderWindow;
use App\Models\Meeting;
use App\Notifications\Meeting\MeetingReminderNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingReminderNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_eve_notification_content(): void
    {
        $meeting = Meeting::factory()->reserved()->create(['scheduled_at' => Carbon::parse('2026-10-25 14:00:00')]);
        $certificationName = $meeting->enrollment->certification->name;

        $data = (new MeetingReminderNotification($meeting, MeetingReminderWindow::Eve))->toArray($meeting->student);

        $this->assertSame('meeting_reminder', $data['notification_type']);
        $this->assertSame('明日の面談のリマインドです', $data['title']);
        $this->assertSame("2026/10/25 14:00〜15:00（{$certificationName}）", $data['message']);
        $this->assertSame(route('meetings.show', $meeting), $data['url']);
    }

    public function test_one_hour_before_notification_title(): void
    {
        $meeting = Meeting::factory()->reserved()->create();

        $data = (new MeetingReminderNotification($meeting, MeetingReminderWindow::OneHourBefore))->toArray($meeting->student);

        $this->assertSame('面談のリマインドです', $data['title']);
    }
}
