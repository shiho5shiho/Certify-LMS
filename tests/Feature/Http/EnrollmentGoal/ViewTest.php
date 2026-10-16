<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 受講登録詳細・一覧での個人目標の見え方を検証する。
 * 本人は一覧 + 操作、担当コーチ / 管理者は一覧のみ(操作なし)、他受講生は詳細自体に入れない。
 */
class ViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_goals_in_display_order_with_operations(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $achieved = EnrollmentGoal::factory()->forEnrollment($enrollment)->achieved()
            ->create(['title' => '達成済の目標']);
        $unachieved = EnrollmentGoal::factory()->forEnrollment($enrollment)
            ->create(['title' => '未達成の目標']);

        // Act
        $response = $this->actingAs($student)->get(route('enrollments.show', $enrollment));

        // Assert
        $response->assertOk();
        $response->assertSeeInOrder(['未達成の目標', '達成済の目標']);
        $response->assertSee(route('enrollments.goals.store', $enrollment));
        $response->assertSee(route('enrollment-goals.markAchieved', $unachieved));
        $response->assertSee(route('enrollment-goals.unmarkAchieved', $achieved));
        $response->assertSee(route('enrollment-goals.edit', $achieved));
        $response->assertSee(route('enrollment-goals.destroy', $achieved));
    }

    public function test_assigned_coach_sees_goals_without_operations(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $this->attachCoach($enrollment->certification, $coach);
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create(['title' => '受講生の目標']);

        // Act
        $response = $this->actingAs($coach)->get(route('enrollments.show', $enrollment));

        // Assert
        $response->assertOk();
        $response->assertSee('受講生の目標');
        $this->assertNoGoalOperations($response, $enrollment, $goal);
    }

    public function test_admin_sees_goals_without_operations(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create(['title' => '受講生の目標']);

        // Act
        $response = $this->actingAs($admin)->get(route('enrollments.show', $enrollment));

        // Assert
        $response->assertOk();
        $response->assertSee('受講生の目標');
        $this->assertNoGoalOperations($response, $enrollment, $goal);
    }

    public function test_other_student_cannot_view_enrollment_with_goals(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $otherEnrollment = Enrollment::factory()->learning()->create();
        EnrollmentGoal::factory()->forEnrollment($otherEnrollment)->create(['title' => '他人の目標']);

        // Act
        $response = $this->actingAs($student)->get(route('enrollments.show', $otherEnrollment));

        // Assert
        $response->assertForbidden();
        $response->assertDontSee('他人の目標');
    }

    public function test_index_shows_goals_count_per_enrollment(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        EnrollmentGoal::factory()->count(2)->forEnrollment($enrollment)->create();
        EnrollmentGoal::factory()->create();

        // Act
        $response = $this->actingAs($student)->get(route('enrollments.index'));

        // Assert
        $response->assertOk();
        $response->assertViewHas('enrollments', fn ($enrollments) => $enrollments->firstWhere('id', $enrollment->id)?->goals_count === 2);
    }

    private function assertNoGoalOperations($response, Enrollment $enrollment, EnrollmentGoal $goal): void
    {
        $response->assertDontSee(route('enrollments.goals.store', $enrollment));
        $response->assertDontSee(route('enrollment-goals.markAchieved', $goal));
        $response->assertDontSee(route('enrollment-goals.edit', $goal));
        $response->assertDontSee(route('enrollment-goals.destroy', $goal));
    }

    private function attachCoach(Certification $certification, User $coach): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }
}
