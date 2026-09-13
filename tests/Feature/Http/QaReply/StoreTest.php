<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaReply;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_reply_to_visible_thread(): void
    {
        // Arrange
        $author = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($certification)->create();

        // Act
        $response = $this->actingAs($author)->post(route('qa-board.replies.store', $thread), [
            'body' => '回答本文',
        ]);

        // Assert
        $response->assertRedirect();
        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $author->id,
            'body' => '回答本文',
        ]);
    }

    public function test_assigned_coach_can_reply(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $thread = QaThread::factory()->for($certification)->create();

        // Act
        $response = $this->actingAs($coach)->post(route('qa-board.replies.store', $thread), [
            'body' => 'コーチからの回答',
        ]);

        // Assert
        $response->assertRedirect();
        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $coach->id,
        ]);
    }

    public function test_unassigned_coach_cannot_reply(): void
    {
        // Arrange: certification に coach を紐付けない
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($certification)->create();

        // Act
        $response = $this->actingAs($coach)->post(route('qa-board.replies.store', $thread), [
            'body' => 'コーチからの回答',
        ]);

        // Assert
        $response->assertForbidden();
    }

    public function test_admin_cannot_reply(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('qa-board.replies.store', $thread), [
            'body' => '管理者からの回答',
        ]);

        // Assert
        $response->assertForbidden();
    }

    public function test_body_over_5000_characters_is_rejected(): void
    {
        // Arrange
        $author = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($certification)->create();

        // Act
        $response = $this->actingAs($author)->post(route('qa-board.replies.store', $thread), [
            'body' => str_repeat('あ', 5001),
        ]);

        // Assert
        $response->assertSessionHasErrors('body');
    }
}
