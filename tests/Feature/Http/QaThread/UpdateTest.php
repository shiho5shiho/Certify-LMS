<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_own_thread(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create(['user_id' => $student->id]);

        // Act
        $response = $this->actingAs($student)->patch(route('qa-board.update', $thread), [
            'title' => '更新後タイトル',
            'body' => '更新後本文',
        ]);

        // Assert
        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'title' => '更新後タイトル',
        ]);
    }

    public function test_other_student_cannot_update_thread(): void
    {
        // Arrange
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create(['user_id' => $owner->id]);

        // Act
        $response = $this->actingAs($other)->patch(route('qa-board.update', $thread), [
            'title' => '更新後タイトル',
            'body' => '更新後本文',
        ]);

        // Assert
        $response->assertForbidden();
    }

    public function test_certification_id_is_not_changeable_via_update(): void
    {
        // Arrange: リクエストに certification_id を混ぜて送っても無視されることを確認する
        $student = User::factory()->student()->inProgress()->create();
        $original = Certification::factory()->published()->create();
        $another = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($original)->create(['user_id' => $student->id]);

        // Act
        $this->actingAs($student)->patch(route('qa-board.update', $thread), [
            'certification_id' => $another->id,
            'title' => '更新後タイトル',
            'body' => '更新後本文',
        ]);

        // Assert
        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'certification_id' => $original->id,
        ]);
    }

    public function test_title_over_200_characters_is_rejected(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create(['user_id' => $student->id]);

        // Act
        $response = $this->actingAs($student)->patch(route('qa-board.update', $thread), [
            'title' => str_repeat('あ', 201),
            'body' => '更新後本文',
        ]);

        // Assert
        $response->assertSessionHasErrors('title');
    }
}
