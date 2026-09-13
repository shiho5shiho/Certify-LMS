<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_delete_own_reply(): void
    {
        // Arrange
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($thread, 'thread')->create(['user_id' => $author->id]);

        // Act
        $response = $this->actingAs($author)->delete(route('qa-board.replies.destroy', [
            'thread' => $thread,
            'reply' => $reply,
        ]));

        // Assert
        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);
    }

    public function test_other_user_cannot_delete_reply(): void
    {
        // Arrange
        $author = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($thread, 'thread')->create(['user_id' => $author->id]);

        // Act
        $response = $this->actingAs($other)->delete(route('qa-board.replies.destroy', [
            'thread' => $thread,
            'reply' => $reply,
        ]));

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseHas('qa_replies', ['id' => $reply->id]);
    }

    public function test_admin_can_delete_any_reply(): void
    {
        // Arrange: 管理者はモデレーションとして誰の回答でも削除できる
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($thread, 'thread')->create();

        // Act
        $response = $this->actingAs($admin)->delete(route('admin.qa-board.replies.destroy', [
            'thread' => $thread,
            'reply' => $reply,
        ]));

        // Assert
        $response->assertRedirect(route('admin.qa-board.show', $thread));
        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);
    }

    public function test_reply_not_belonging_to_thread_returns_404(): void
    {
        // Arrange
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create();
        $otherThread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($otherThread, 'thread')->create(['user_id' => $author->id]);

        // Act
        $response = $this->actingAs($author)->delete(route('qa-board.replies.destroy', [
            'thread' => $thread,
            'reply' => $reply,
        ]));

        // Assert
        $response->assertNotFound();
    }
}
