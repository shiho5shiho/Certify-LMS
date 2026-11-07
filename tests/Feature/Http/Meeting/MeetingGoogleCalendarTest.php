<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Meeting;

use App\Enums\MeetingStatus;
use App\Exceptions\Mentoring\GoogleCalendarCredentialRevokedException;
use App\Exceptions\Mentoring\GoogleCalendarRequestFailedException;
use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\GoogleCalendarCredential;
use App\Models\Meeting;
use App\Models\User;
use App\Services\Contracts\GoogleCalendarServiceContract;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Google カレンダー連携が、面談の空き枠・予約・キャンセルに反映されることのテスト。
 * Google との通信は GoogleCalendarServiceContract のモックに差し替える。
 */
class MeetingGoogleCalendarTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $coach;

    private Enrollment $enrollment;

    private Carbon $nextMonday;

    protected function setUp(): void
    {
        parent::setUp();

        // 次の月曜 09:00〜12:00 に枠を持つコーチ 1 名と、その資格を受講中の受講生
        $this->student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
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
        CoachAvailability::factory()->forCoach($this->coach)->onDay(1)->timeRange('09:00:00', '12:00:00')->create();
        $this->enrollment = Enrollment::factory()->for($this->student, 'user')->for($certification)->learning()->create();
        $this->nextMonday = now()->startOfDay()->next(Carbon::MONDAY);
    }

    public function test_availability_excludes_slot_busy_on_google_calendar(): void
    {
        GoogleCalendarCredential::factory()->forCoach($this->coach)->create();
        $busyStart = $this->nextMonday->copy()->setTime(10, 30);
        $this->mock(GoogleCalendarServiceContract::class, function (MockInterface $mock) use ($busyStart) {
            $mock->shouldReceive('busyIntervals')->once()->andReturn([
                ['start' => $busyStart, 'end' => $busyStart->copy()->addMinutes(30)],
            ]);
        });

        $slots = $this->fetchSlotStartTimes();

        $this->assertSame(['09:00', '11:00'], $slots);
    }

    public function test_availability_excludes_all_slots_when_google_request_fails(): void
    {
        GoogleCalendarCredential::factory()->forCoach($this->coach)->create();
        $this->mock(GoogleCalendarServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('busyIntervals')->once()->andThrow(new GoogleCalendarRequestFailedException);
        });

        $slots = $this->fetchSlotStartTimes();

        $this->assertSame([], $slots);
    }

    public function test_availability_for_unlinked_coach_does_not_call_google(): void
    {
        $this->mock(GoogleCalendarServiceContract::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('busyIntervals');
        });

        $slots = $this->fetchSlotStartTimes();

        $this->assertSame(['09:00', '10:00', '11:00'], $slots);
    }

    public function test_availability_treats_revoked_coach_as_unlinked(): void
    {
        GoogleCalendarCredential::factory()->forCoach($this->coach)->create();
        $this->mock(GoogleCalendarServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('busyIntervals')->once()->andThrow(new GoogleCalendarCredentialRevokedException);
        });

        $slots = $this->fetchSlotStartTimes();

        // 連携が無効になったコーチは、未連携のコーチと同じく Google の予定を見ずに判定する
        $this->assertSame(['09:00', '10:00', '11:00'], $slots);
    }

    public function test_store_registers_event_and_saves_event_id(): void
    {
        GoogleCalendarCredential::factory()->forCoach($this->coach)->create();
        $this->mock(GoogleCalendarServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('busyIntervals')->andReturn([]);
            $mock->shouldReceive('createEvent')->once()->andReturn('evt-123');
        });

        $this->reserve()->assertRedirect();

        $this->assertDatabaseHas('meetings', [
            'coach_id' => $this->coach->id,
            'status' => MeetingStatus::Reserved->value,
            'google_event_id' => 'evt-123',
        ]);
    }

    public function test_store_completes_even_when_event_registration_fails(): void
    {
        GoogleCalendarCredential::factory()->forCoach($this->coach)->create();
        $this->mock(GoogleCalendarServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('busyIntervals')->andReturn([]);
            $mock->shouldReceive('createEvent')->once()->andThrow(new GoogleCalendarRequestFailedException);
        });

        $this->reserve()->assertSessionHas('success', '面談を予約しました。');

        $this->assertDatabaseHas('meetings', [
            'coach_id' => $this->coach->id,
            'status' => MeetingStatus::Reserved->value,
            'google_event_id' => null,
        ]);
    }

    public function test_store_for_unlinked_coach_does_not_register_event(): void
    {
        $this->mock(GoogleCalendarServiceContract::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('createEvent');
        });

        $this->reserve()->assertSessionHas('success', '面談を予約しました。');

        $this->assertDatabaseHas('meetings', [
            'coach_id' => $this->coach->id,
            'google_event_id' => null,
        ]);
    }

    public function test_store_completes_without_event_when_credential_is_revoked(): void
    {
        GoogleCalendarCredential::factory()->forCoach($this->coach)->create();
        $this->mock(GoogleCalendarServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('busyIntervals')->andReturn([]);
            $mock->shouldReceive('createEvent')->once()->andThrow(new GoogleCalendarCredentialRevokedException);
        });

        $this->reserve()->assertSessionHas('success', '面談を予約しました。');

        $this->assertDatabaseHas('meetings', [
            'coach_id' => $this->coach->id,
            'status' => MeetingStatus::Reserved->value,
            'google_event_id' => null,
        ]);
    }

    public function test_cancel_deletes_registered_event(): void
    {
        GoogleCalendarCredential::factory()->forCoach($this->coach)->create();
        $meeting = $this->createReservedMeeting('evt-123');
        $this->mock(GoogleCalendarServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('deleteEvent')->once()->withArgs(fn ($credential, $eventId) => $eventId === 'evt-123');
        });

        $this->actingAs($this->student)->post(route('meetings.cancel', $meeting))->assertRedirect();

        $this->assertSame(MeetingStatus::Canceled, $meeting->fresh()->status);
    }

    public function test_cancel_completes_even_when_event_deletion_fails(): void
    {
        GoogleCalendarCredential::factory()->forCoach($this->coach)->create();
        $meeting = $this->createReservedMeeting('evt-123');
        $this->mock(GoogleCalendarServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('deleteEvent')->once()->andThrow(new GoogleCalendarRequestFailedException);
        });

        $this->actingAs($this->student)
            ->post(route('meetings.cancel', $meeting))
            ->assertSessionHas('success', '面談をキャンセルしました。面談回数を返却しました。');

        $this->assertSame(MeetingStatus::Canceled, $meeting->fresh()->status);
    }

    public function test_cancel_completes_when_credential_is_revoked(): void
    {
        GoogleCalendarCredential::factory()->forCoach($this->coach)->create();
        $meeting = $this->createReservedMeeting('evt-123');
        $this->mock(GoogleCalendarServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('deleteEvent')->once()->andThrow(new GoogleCalendarCredentialRevokedException);
        });

        $this->actingAs($this->student)
            ->post(route('meetings.cancel', $meeting))
            ->assertSessionHas('success', '面談をキャンセルしました。面談回数を返却しました。');

        $this->assertSame(MeetingStatus::Canceled, $meeting->fresh()->status);
    }

    /**
     * 次の月曜の空き枠の開始時刻(H:i)一覧を返す。
     *
     * @return list<string>
     */
    private function fetchSlotStartTimes(): array
    {
        $response = $this->actingAs($this->student)->getJson(
            route('meetings.availability', $this->enrollment).'?date='.$this->nextMonday->format('Y-m-d')
        );
        $response->assertOk();

        return array_map(
            fn (array $slot) => Carbon::parse($slot['slot_start'])->format('H:i'),
            $response->json('slots'),
        );
    }

    private function reserve(): TestResponse
    {
        return $this->actingAs($this->student)->post(route('meetings.store', $this->enrollment), [
            'scheduled_at' => $this->nextMonday->copy()->setTime(10, 0)->format('Y-m-d\TH:i:s'),
            'topic' => '相談したい',
        ]);
    }

    private function createReservedMeeting(string $googleEventId): Meeting
    {
        return Meeting::factory()->reserved()->forCoach($this->coach)->forStudent($this->student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
            'google_event_id' => $googleEventId,
        ]);
    }
}
