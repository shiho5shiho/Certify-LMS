<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AchieveTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_mark_goal_as_achieved(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        // Act
        $response = $this->actingAs($student)->post(route('enrollment-goals.markAchieved', $goal));

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success', '目標を達成済みにしました。');
        $this->assertNotNull($goal->refresh()->achieved_at);
    }

    public function test_marking_already_achieved_goal_keeps_first_achieved_at(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $firstAchievedAt = Carbon::parse('2026-10-01 10:00:00');
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create([
            'achieved_at' => $firstAchievedAt,
        ]);

        // Act
        $response = $this->actingAs($student)->post(route('enrollment-goals.markAchieved', $goal));

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHasNoErrors();
        $this->assertTrue($goal->refresh()->achieved_at->equalTo($firstAchievedAt));
    }

    public function test_owner_can_unmark_achieved_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->achieved()->create();

        // Act
        $response = $this->actingAs($student)->delete(route('enrollment-goals.unmarkAchieved', $goal));

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success', '目標を未達成に戻しました。');
        $this->assertNull($goal->refresh()->achieved_at);
    }

    public function test_unmarking_unachieved_goal_succeeds_without_change(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        // Act
        $response = $this->actingAs($student)->delete(route('enrollment-goals.unmarkAchieved', $goal));

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHasNoErrors();
        $this->assertNull($goal->refresh()->achieved_at);
    }

    public function test_other_student_cannot_mark_or_unmark_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $unachieved = EnrollmentGoal::factory()->create();
        $achieved = EnrollmentGoal::factory()->achieved()->create();

        // Act & Assert
        $this->actingAs($student)
            ->post(route('enrollment-goals.markAchieved', $unachieved))
            ->assertForbidden();
        $this->actingAs($student)
            ->delete(route('enrollment-goals.unmarkAchieved', $achieved))
            ->assertForbidden();

        $this->assertNull($unachieved->refresh()->achieved_at);
        $this->assertNotNull($achieved->refresh()->achieved_at);
    }

    public function test_coach_cannot_mark_goal(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $goal = EnrollmentGoal::factory()->create();

        // Act
        $response = $this->actingAs($coach)->post(route('enrollment-goals.markAchieved', $goal));

        // Assert
        $response->assertForbidden();
        $this->assertNull($goal->refresh()->achieved_at);
    }
}
