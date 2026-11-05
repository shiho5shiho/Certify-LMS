<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\IndexAsCoachAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexAsCoachActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_upcoming_returns_own_meetings_in_ascending_order(): void
    {
        $coach = User::factory()->coach()->create();
        $later = Meeting::factory()->reserved()->forCoach($coach)->create(['scheduled_at' => now()->addDays(5)->startOfHour()]);
        $sooner = Meeting::factory()->reserved()->forCoach($coach)->create(['scheduled_at' => now()->addDays(2)->startOfHour()]);
        Meeting::factory()->reserved()->inFuture()->create(); // 他のコーチの面談

        $result = app(IndexAsCoachAction::class)($coach, 'upcoming', null, null);

        $this->assertSame([$sooner->id, $later->id], $result->pluck('id')->all());
    }

    public function test_filters_by_student(): void
    {
        $coach = User::factory()->coach()->create();
        $studentA = User::factory()->student()->create();
        $studentB = User::factory()->student()->create();
        $meetingA = Meeting::factory()->reserved()->forCoach($coach)->forStudent($studentA)->create(['scheduled_at' => now()->addDays(2)->startOfHour()]);
        Meeting::factory()->reserved()->forCoach($coach)->forStudent($studentB)->create(['scheduled_at' => now()->addDays(3)->startOfHour()]);

        $result = app(IndexAsCoachAction::class)($coach, 'upcoming', $studentA->id, null);

        $this->assertSame([$meetingA->id], $result->pluck('id')->all());
    }
}
