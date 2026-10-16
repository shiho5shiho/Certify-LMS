<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentGoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_display_order_puts_unachieved_first_then_nearest_target_date_with_null_last(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create();
        $achieved = EnrollmentGoal::factory()->forEnrollment($enrollment)->achieved()
            ->create(['target_date' => now()->addDay()->toDateString()]);
        $far = EnrollmentGoal::factory()->forEnrollment($enrollment)
            ->create(['target_date' => now()->addDays(10)->toDateString()]);
        $noDate = EnrollmentGoal::factory()->forEnrollment($enrollment)
            ->create(['target_date' => null]);
        $near = EnrollmentGoal::factory()->forEnrollment($enrollment)
            ->create(['target_date' => now()->addDays(3)->toDateString()]);

        // Act
        $ids = EnrollmentGoal::query()->displayOrder()->pluck('id')->all();

        // Assert
        $this->assertSame([$near->id, $far->id, $noDate->id, $achieved->id], $ids);
    }

    public function test_display_order_puts_newer_first_when_target_date_is_same(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create();
        $date = now()->addDays(5)->toDateString();
        $older = EnrollmentGoal::factory()->forEnrollment($enrollment)
            ->create(['target_date' => $date, 'created_at' => now()->subDay()]);
        $newer = EnrollmentGoal::factory()->forEnrollment($enrollment)
            ->create(['target_date' => $date, 'created_at' => now()]);

        // Act
        $ids = EnrollmentGoal::query()->displayOrder()->pluck('id')->all();

        // Assert
        $this->assertSame([$newer->id, $older->id], $ids);
    }

    public function test_is_achieved_reflects_achieved_at(): void
    {
        $this->assertFalse(EnrollmentGoal::factory()->make()->isAchieved());
        $this->assertTrue(EnrollmentGoal::factory()->achieved()->make()->isAchieved());
    }
}
