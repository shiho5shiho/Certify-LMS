<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\IndexAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_upcoming_returns_only_own_future_reserved_meetings(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $upcoming = Meeting::factory()->reserved()->inFuture()->forStudent($student)->create();
        Meeting::factory()->canceled()->inPast()->forStudent($student)->create();
        Meeting::factory()->reserved()->inFuture()->create(); // 他の受講生の面談

        $result = app(IndexAction::class)($student, 'upcoming');

        $this->assertSame([$upcoming->id], $result['meetings']->pluck('id')->all());
    }

    public function test_past_returns_canceled_and_completed_meetings(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $canceled = Meeting::factory()->canceled()->inPast()->forStudent($student)->create();
        $completed = Meeting::factory()->completed()->forStudent($student)->create();
        Meeting::factory()->reserved()->inFuture()->forStudent($student)->create();

        $result = app(IndexAction::class)($student, 'past');

        $this->assertEqualsCanonicalizing(
            [$canceled->id, $completed->id],
            $result['meetings']->pluck('id')->all(),
        );
    }

    public function test_returns_remaining_quota(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);

        $result = app(IndexAction::class)($student, 'upcoming');

        $this->assertSame(3, $result['meetingsRemaining']);
    }
}
