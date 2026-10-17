<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_edit_page(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        // Act
        $response = $this->actingAs($student)->get(route('enrollment-goals.edit', $goal));

        // Assert
        $response->assertOk();
        $response->assertViewIs('enrollment-goal.edit');
    }

    public function test_other_student_cannot_view_edit_page(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $goal = EnrollmentGoal::factory()->create();

        // Act
        $response = $this->actingAs($student)->get(route('enrollment-goals.edit', $goal));

        // Assert
        $response->assertForbidden();
    }

    public function test_coach_cannot_view_edit_page(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $goal = EnrollmentGoal::factory()->create();

        // Act
        $response = $this->actingAs($coach)->get(route('enrollment-goals.edit', $goal));

        // Assert
        $response->assertForbidden();
    }

    public function test_owner_can_update_goal_without_changing_achieved_at(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $achievedAt = Carbon::parse('2026-10-01 10:00:00');
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create([
            'title' => '変更前',
            'achieved_at' => $achievedAt,
        ]);

        // Act
        $response = $this->actingAs($student)->patch(route('enrollment-goals.update', $goal), [
            'title' => '変更後',
            'description' => '詳細を追加',
            'target_date' => now()->subDays(3)->toDateString(),
        ]);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success', '目標を更新しました。');
        $goal->refresh();
        $this->assertSame('変更後', $goal->title);
        $this->assertSame('詳細を追加', $goal->description);
        $this->assertSame(now()->subDays(3)->toDateString(), $goal->target_date->toDateString());
        $this->assertTrue($goal->achieved_at->equalTo($achievedAt));
    }

    public function test_update_fails_without_title(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create(['title' => '変更前']);

        // Act
        $response = $this->actingAs($student)->patch(route('enrollment-goals.update', $goal), [
            'title' => '',
        ]);

        // Assert
        $response->assertSessionHasErrors(['title' => '目標 を入力してください。']);
        $this->assertSame('変更前', $goal->refresh()->title);
    }

    public function test_other_student_cannot_update_goal(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $goal = EnrollmentGoal::factory()->create(['title' => '変更前']);

        // Act
        $response = $this->actingAs($student)->patch(route('enrollment-goals.update', $goal), [
            'title' => '変更後',
        ]);

        // Assert
        $response->assertForbidden();
        $this->assertSame('変更前', $goal->refresh()->title);
    }
}
