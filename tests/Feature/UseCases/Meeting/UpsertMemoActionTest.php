<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Exceptions\Mentoring\MeetingStatusTransitionException;
use App\Models\Meeting;
use App\UseCases\Meeting\UpsertMemoAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpsertMemoActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_memo(): void
    {
        $meeting = Meeting::factory()->reserved()->create();

        app(UpsertMemoAction::class)($meeting, '初回メモ');

        $this->assertDatabaseHas('meeting_memos', [
            'meeting_id' => $meeting->id,
            'body' => '初回メモ',
        ]);
    }

    public function test_updates_existing_memo(): void
    {
        $meeting = Meeting::factory()->reserved()->create();

        app(UpsertMemoAction::class)($meeting, '初回メモ');
        app(UpsertMemoAction::class)($meeting, '更新後メモ');

        // 2 件目は作られず、1 件が上書きされる
        $this->assertDatabaseCount('meeting_memos', 1);
        $this->assertDatabaseHas('meeting_memos', [
            'meeting_id' => $meeting->id,
            'body' => '更新後メモ',
        ]);
    }

    public function test_throws_for_canceled_meeting(): void
    {
        $meeting = Meeting::factory()->canceled()->create();

        $this->assertThrows(
            fn () => app(UpsertMemoAction::class)($meeting, 'メモ'),
            MeetingStatusTransitionException::class,
        );

        $this->assertDatabaseCount('meeting_memos', 0);
    }
}
