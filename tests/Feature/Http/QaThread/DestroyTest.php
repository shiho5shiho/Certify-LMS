<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 回答が 0 件の場合のみ投稿者本人が削除可能。
 * 1 件でも回答が付いている場合は投稿者本人でも削除不可(admin のみ削除可)。
 */
class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_delete_thread_with_no_replies(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create(['user_id' => $student->id]);

        // Act
        $response = $this->actingAs($student)->delete(route('qa-board.destroy', $thread));

        // Assert
        $response->assertRedirect(route('qa-board.index'));
        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
    }

    public function test_owner_cannot_delete_thread_with_replies(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create(['user_id' => $student->id]);
        QaReply::factory()->for($thread, 'thread')->create();

        // Act
        $response = $this->actingAs($student)->delete(route('qa-board.destroy', $thread));

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseHas('qa_threads', ['id' => $thread->id]);
    }

    public function test_admin_can_delete_thread_even_with_replies(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();
        QaReply::factory()->for($thread, 'thread')->create();

        // Act
        $response = $this->actingAs($admin)->delete(route('admin.qa-board.destroy', $thread));

        // Assert
        $response->assertRedirect(route('admin.qa-board.index'));
        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
    }

    public function test_deleting_thread_cascades_to_replies(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();
        QaReply::factory()->for($thread, 'thread')->create();

        // Act
        $this->actingAs($admin)->delete(route('admin.qa-board.destroy', $thread));

        // Assert
        $this->assertDatabaseMissing('qa_replies', ['qa_thread_id' => $thread->id]);
    }
}
