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

class ViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_coach_sees_operations_only_on_own_notes(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $this->attachCoach($enrollment->certification, $coach);
        $this->attachCoach($enrollment->certification, $otherCoach);
        $own = EnrollmentNote::factory()->forEnrollment($enrollment)->byAuthor($coach)->create(['body' => '自分のメモ']);
        $others = EnrollmentNote::factory()->forEnrollment($enrollment)->byAuthor($otherCoach)->create(['body' => '他コーチのメモ']);

        // Act
        $response = $this->actingAs($coach)->get(route('enrollments.show', $enrollment));

        // Assert
        $response->assertOk();
        $response->assertSee('自分のメモ');
        $response->assertSee('他コーチのメモ');
        $response->assertSee(route('enrollment-notes.edit', $own));
        $response->assertSee(route('enrollment-notes.destroy', $own));
        $response->assertDontSee(route('enrollment-notes.edit', $others));
        $response->assertDontSee(route('enrollment-notes.destroy', $others));
    }

    public function test_admin_sees_operations_on_all_notes(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->create(['body' => 'コーチのメモ']);

        // Act
        $response = $this->actingAs($admin)->get(route('enrollments.show', $enrollment));

        // Assert
        $response->assertOk();
        $response->assertSee('コーチのメモ');
        $response->assertSee(route('enrollments.notes.store', $enrollment));
        $response->assertSee(route('enrollment-notes.edit', $note));
        $response->assertSee(route('enrollment-notes.destroy', $note));
    }

    public function test_student_does_not_see_note_section_on_own_enrollment(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        EnrollmentNote::factory()->forEnrollment($enrollment)->create(['body' => '受講生に見せないメモ']);

        // Act
        $response = $this->actingAs($student)->get(route('enrollments.show', $enrollment));

        // Assert
        $response->assertOk();
        $response->assertDontSee('コーチメモ');
        $response->assertDontSee('受講生に見せないメモ');
    }

    public function test_unenroll_keeps_notes_but_hides_them(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->create(['body' => '解除前のメモ']);

        // Act: 受講生が受講解除(論理削除)
        $this->actingAs($student)->delete(route('enrollments.destroy', $enrollment));
        $response = $this->actingAs($admin)->get(route('enrollments.show', $enrollment));

        // Assert
        $this->assertModelExists($note);
        $response->assertOk();
        $response->assertDontSee('解除前のメモ');
        $response->assertDontSee(route('enrollments.notes.store', $enrollment));
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
