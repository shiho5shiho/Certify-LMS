<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 親の受講登録が削除されたときに、配下の個人目標も連動して削除されることを検証する。
 */
class EnrollmentDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_unenrolling_deletes_goals_of_the_enrollment(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goals = EnrollmentGoal::factory()->count(2)->forEnrollment($enrollment)->create();
        $otherGoal = EnrollmentGoal::factory()->create();

        // Act
        $response = $this->actingAs($student)->delete(route('enrollments.destroy', $enrollment));

        // Assert
        $response->assertRedirect(route('enrollments.index'));
        $this->assertSoftDeleted('enrollments', ['id' => $enrollment->id]);
        foreach ($goals as $goal) {
            $this->assertDatabaseMissing('enrollment_goals', ['id' => $goal->id]);
        }
        $this->assertDatabaseHas('enrollment_goals', ['id' => $otherGoal->id]);
    }

    public function test_goals_remain_when_unenroll_is_rejected(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->passed()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        // Act
        $response = $this->actingAs($student)->delete(route('enrollments.destroy', $enrollment));

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseHas('enrollment_goals', ['id' => $goal->id]);
    }

    public function test_force_deleting_enrollment_cascades_to_goals(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        // Act
        $enrollment->forceDelete();

        // Assert
        $this->assertDatabaseMissing('enrollment_goals', ['id' => $goal->id]);
    }
}
