<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_destroy_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        // Act
        $response = $this->actingAs($student)->delete(route('enrollment-goals.destroy', $goal));

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success', '目標を削除しました。');
        $this->assertDatabaseMissing('enrollment_goals', ['id' => $goal->id]);
    }

    public function test_other_student_cannot_destroy_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $goal = EnrollmentGoal::factory()->create();

        // Act
        $response = $this->actingAs($student)->delete(route('enrollment-goals.destroy', $goal));

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseHas('enrollment_goals', ['id' => $goal->id]);
    }

    public function test_coach_cannot_destroy_goal(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $goal = EnrollmentGoal::factory()->create();

        // Act
        $response = $this->actingAs($coach)->delete(route('enrollment-goals.destroy', $goal));

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseHas('enrollment_goals', ['id' => $goal->id]);
    }
}
