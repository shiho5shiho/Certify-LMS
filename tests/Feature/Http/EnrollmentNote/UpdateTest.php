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

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_coach_can_open_edit_page_and_update_note(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $this->attachCoach($enrollment->certification, $coach);
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->byAuthor($coach)->create();

        // Act
        $editResponse = $this->actingAs($coach)->get(route('enrollment-notes.edit', $note));
        $updateResponse = $this->actingAs($coach)->patch(route('enrollment-notes.update', $note), [
            'body' => '更新後の本文',
        ]);

        // Assert
        $editResponse->assertOk();
        $updateResponse->assertRedirect(route('enrollments.show', $enrollment));
        $updateResponse->assertSessionHas('success', 'メモを更新しました。');
        $this->assertSame('更新後の本文', $note->refresh()->body);
    }

    public function test_other_assigned_coach_cannot_edit_or_update_note(): void
    {
        // Arrange
        $author = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $enrollment = Enrollment::factory()->learning()->create();
        $this->attachCoach($enrollment->certification, $author);
        $this->attachCoach($enrollment->certification, $otherCoach);
        $note = EnrollmentNote::factory()->forEnrollment($enrollment)->byAuthor($author)->create(['body' => '元の本文']);

        // Act
        $editResponse = $this->actingAs($otherCoach)->get(route('enrollment-notes.edit', $note));
        $updateResponse = $this->actingAs($otherCoach)->patch(route('enrollment-notes.update', $note), [
            'body' => '書き換え',
        ]);

        // Assert
        $editResponse->assertForbidden();
        $updateResponse->assertForbidden();
        $this->assertSame('元の本文', $note->refresh()->body);
    }

    public function test_admin_can_update_other_coachs_note(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $note = EnrollmentNote::factory()->create();

        // Act
        $response = $this->actingAs($admin)->patch(route('enrollment-notes.update', $note), [
            'body' => '管理者が是正',
        ]);

        // Assert
        $response->assertRedirect(route('enrollments.show', $note->enrollment_id));
        $this->assertSame('管理者が是正', $note->refresh()->body);
    }

    public function test_author_coach_cannot_update_after_unassigned(): void
    {
        // Arrange: 担当を外れたコーチ(作成者)
        $coach = User::factory()->coach()->create();
        $note = EnrollmentNote::factory()->byAuthor($coach)->create(['body' => '元の本文']);

        // Act
        $response = $this->actingAs($coach)->patch(route('enrollment-notes.update', $note), [
            'body' => '書き換え',
        ]);

        // Assert
        $response->assertForbidden();
        $this->assertSame('元の本文', $note->refresh()->body);
    }

    public function test_cannot_update_note_of_unenrolled_enrollment(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $note = EnrollmentNote::factory()->create(['body' => '元の本文']);
        $note->enrollment->delete();

        // Act
        $response = $this->actingAs($admin)->patch(route('enrollment-notes.update', $note), [
            'body' => '書き換え',
        ]);

        // Assert
        $response->assertForbidden();
        $this->assertSame('元の本文', $note->refresh()->body);
    }

    public function test_update_validates_body(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $note = EnrollmentNote::factory()->create(['body' => '元の本文']);

        // Act & Assert: 空は更新できない
        $this->actingAs($admin)
            ->patch(route('enrollment-notes.update', $note), ['body' => ''])
            ->assertSessionHasErrors(['body' => '本文 を入力してください。']);

        // Act & Assert: 2001 文字は更新できない
        $this->actingAs($admin)
            ->patch(route('enrollment-notes.update', $note), ['body' => str_repeat('あ', 2001)])
            ->assertSessionHasErrors(['body' => '本文 は 2000 文字以内で入力してください。']);

        $this->assertSame('元の本文', $note->refresh()->body);
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
