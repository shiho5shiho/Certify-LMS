<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_coach_can_delete_note(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $this->attachCoach($enrollment->certification, $coach);
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->byAuthor($coach)->create();

        // Act
        $response = $this->actingAs($coach)->delete(route('enrollment-notes.destroy', $note));

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success', 'メモを削除しました。');
        $this->assertModelMissing($note);
    }

    public function test_other_assigned_coach_cannot_delete_note(): void
    {
        // Arrange
        $author = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $this->attachCoach($enrollment->certification, $author);
        $this->attachCoach($enrollment->certification, $otherCoach);
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->byAuthor($author)->create();

        // Act
        $response = $this->actingAs($otherCoach)->delete(route('enrollment-notes.destroy', $note));

        // Assert
        $response->assertForbidden();
        $this->assertModelExists($note);
    }

    public function test_admin_can_delete_any_note(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $note = EnrollmentNote::factory()->create();

        // Act
        $response = $this->actingAs($admin)->delete(route('enrollment-notes.destroy', $note));

        // Assert
        $response->assertRedirect(route('enrollments.show', $note->enrollment_id));
        $this->assertModelMissing($note);
    }

    public function test_student_cannot_delete_note_on_own_enrollment(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->create();

        // Act
        $response = $this->actingAs($student)->delete(route('enrollment-notes.destroy', $note));

        // Assert
        $response->assertForbidden();
        $this->assertModelExists($note);
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
