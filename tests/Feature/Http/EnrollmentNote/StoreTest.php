<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_coach_can_add_note(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $this->attachCoach($enrollment->certification, $coach);

        // Act
        $response = $this->actingAs($coach)->post(route('enrollments.notes.store', $enrollment), [
            'body' => '次回面談で過去問の進み具合を確認する。',
        ]);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $response->assertSessionHas('success', 'メモを追加しました。');
        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'author_id' => $coach->id,
            'body' => '次回面談で過去問の進み具合を確認する。',
        ]);
    }

    public function test_admin_can_add_note_to_any_enrollment(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('enrollments.notes.store', $enrollment), [
            'body' => '運営メモ',
        ]);

        // Assert
        $response->assertRedirect(route('enrollments.show', $enrollment));
        $this->assertDatabaseHas('enrollment_notes', [
            'enrollment_id' => $enrollment->id,
            'author_id' => $admin->id,
        ]);
    }

    public function test_unassigned_coach_cannot_add_note(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->learning()->create();

        // Act
        $response = $this->actingAs($coach)->post(route('enrollments.notes.store', $enrollment), [
            'body' => '担当外のメモ',
        ]);

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseCount('enrollment_notes', 0);
    }

    public function test_student_cannot_add_note_even_to_own_enrollment(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();

        // Act
        $response = $this->actingAs($student)->post(route('enrollments.notes.store', $enrollment), [
            'body' => '受講生のメモ',
        ]);

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseCount('enrollment_notes', 0);
    }

    public function test_cannot_add_note_to_unenrolled_enrollment(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $enrollment->delete();

        // Act
        $response = $this->actingAs($admin)->post(route('enrollments.notes.store', $enrollment), [
            'body' => '解除済みへのメモ',
        ]);

        // Assert
        $response->assertNotFound();
        $this->assertDatabaseCount('enrollment_notes', 0);
    }

    public function test_body_is_required(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('enrollments.notes.store', $enrollment), [
            'body' => '',
        ]);

        // Assert
        $response->assertSessionHasErrors(['body' => '本文 を入力してください。']);
        $this->assertDatabaseCount('enrollment_notes', 0);
    }

        public function test_body_accepts_2000_chars_and_rejects_2001_chars(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->learning()->create();

        // Act & Assert: 2000 文字は保存できる
        $this->actingAs($admin)
            ->post(route('enrollments.notes.store', $enrollment), ['body' => str_repeat('あ', 2000)])
            ->assertSessionHasNoErrors();

        // Act & Assert: 2001 文字は保存できない
        $this->actingAs($admin)
            ->post(route('enrollments.notes.store', $enrollment), ['body' => str_repeat('あ', 2001)])
            ->assertSessionHasErrors(['body' => '本文 は 2000 文字以内で入力してください。']);

        $this->assertDatabaseCount('enrollment_notes', 1);
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
