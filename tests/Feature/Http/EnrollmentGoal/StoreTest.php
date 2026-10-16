<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_store_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        // Act
        $response = $this->actingAs($student)->post(route('enrollments.goals.store', $enrollment), [
            'title' => '過去問を解く',
            'description' => '午前問題を中心に',
            'target_date' => now()->addDays(7)->toDateString(),
        ]);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success', '目標を追加しました。');
        $this->assertDatabaseHas('enrollment_goals', [
            'enrollment_id' => $enrollment->id,
            'title' => '過去問を解く',
            'description' => '午前問題を中心に',
            'target_date' => now()->addDays(7)->toDateString(),
            'achieved_at' => null,
        ]);
    }

    public function test_owner_can_store_goal_with_title_only(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        // Act
        $response = $this->actingAs($student)->post(route('enrollments.goals.store', $enrollment), [
            'title' => '過去問を解く',
        ]);

        // Assert
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('enrollment_goals', [
            'enrollment_id' => $enrollment->id,
            'description' => null,
            'target_date' => null,
        ]);
    }

    public function test_owner_can_store_goal_with_past_target_date(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        // Act
        $response = $this->actingAs($student)->post(route('enrollments.goals.store', $enrollment), [
            'title' => '過去問を解く',
            'target_date' => now()->subDay()->toDateString(),
        ]);

        // Assert
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('enrollment_goals', [
            'enrollment_id' => $enrollment->id,
            'target_date' => now()->subDay()->toDateString(),
        ]);
    }

    public function test_store_fails_without_title(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        // Act
        $response = $this->actingAs($student)->post(route('enrollments.goals.store', $enrollment), [
            'title' => '',
        ]);

        // Assert
        $response->assertSessionHasErrors(['title' => '目標 を入力してください。']);
        $this->assertDatabaseCount('enrollment_goals', 0);
    }

    public function test_store_fails_when_title_or_description_exceeds_max_length(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        // Act
        $response = $this->actingAs($student)->post(route('enrollments.goals.store', $enrollment), [
            'title' => str_repeat('あ', 101),
            'description' => str_repeat('あ', 1001),
        ]);

        // Assert
        $response->assertSessionHasErrors(['title', 'description']);
        $this->assertDatabaseCount('enrollment_goals', 0);
    }

    public function test_store_succeeds_at_max_length(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        // Act
        $response = $this->actingAs($student)->post(route('enrollments.goals.store', $enrollment), [
            'title' => str_repeat('あ', 100),
            'description' => str_repeat('あ', 1000),
        ]);

        // Assert
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('enrollment_goals', 1);
    }

    public function test_store_fails_with_invalid_target_date(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        // Act
        $response = $this->actingAs($student)->post(route('enrollments.goals.store', $enrollment), [
            'title' => '過去問を解く',
            'target_date' => 'not-a-date',
        ]);

        // Assert
        $response->assertSessionHasErrors('target_date');
        $this->assertDatabaseCount('enrollment_goals', 0);
    }

    public function test_other_student_cannot_store_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $otherEnrollment = Enrollment::factory()->learning()->create();

        // Act
        $response = $this->actingAs($student)->post(route('enrollments.goals.store', $otherEnrollment), [
            'title' => '過去問を解く',
        ]);

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseCount('enrollment_goals', 0);
    }

    public function test_assigned_coach_cannot_store_goal(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $this->attachCoach($enrollment->certification, $coach);

        // Act
        $response = $this->actingAs($coach)->post(route('enrollments.goals.store', $enrollment), [
            'title' => '過去問を解く',
        ]);

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseCount('enrollment_goals', 0);
    }

    public function test_admin_cannot_store_goal(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('enrollments.goals.store', $enrollment), [
            'title' => '過去問を解く',
        ]);

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseCount('enrollment_goals', 0);
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
