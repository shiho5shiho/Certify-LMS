<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_update_own_reply(): void
    {
        // Arrange
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($thread, 'thread')->create(['user_id' => $author->id]);

        // Act
        $response = $this->actingAs($author)->patch(route('qa-board.replies.update', [
            'thread' => $thread,
            'reply' => $reply,
        ]), [
            'body' => '更新後の回答本文',
        ]);

        // Assert
        $response->assertRedirect();
        $this->assertDatabaseHas('qa_replies', [
            'id' => $reply->id,
            'body' => '更新後の回答本文',
        ]);
    }

    public function test_other_user_cannot_update_reply(): void
    {
        // Arrange
        $author = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($thread, 'thread')->create(['user_id' => $author->id]);

        // Act
        $response = $this->actingAs($other)->patch(route('qa-board.replies.update', [
            'thread' => $thread,
            'reply' => $reply,
        ]), [
            'body' => '更新後の回答本文',
        ]);

        // Assert
        $response->assertForbidden();
    }

    public function test_reply_not_belonging_to_thread_returns_404(): void
    {
        // Arrange: reply は otherThread に属するが、URL には別の thread を指定する
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create();
        $otherThread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($otherThread, 'thread')->create(['user_id' => $author->id]);

        // Act
        $response = $this->actingAs($author)->patch(route('qa-board.replies.update', [
            'thread' => $thread,
            'reply' => $reply,
        ]), [
            'body' => '更新後の回答本文',
        ]);

        // Assert
        $response->assertNotFound();
    }

    public function test_body_over_5000_characters_is_rejected(): void
    {
        // Arrange
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($thread, 'thread')->create(['user_id' => $author->id]);

        // Act
        $response = $this->actingAs($author)->patch(route('qa-board.replies.update', [
            'thread' => $thread,
            'reply' => $reply,
        ]), [
            'body' => str_repeat('あ', 5001),
        ]);

        // Assert
        $response->assertSessionHasErrors('body');
    }
}
