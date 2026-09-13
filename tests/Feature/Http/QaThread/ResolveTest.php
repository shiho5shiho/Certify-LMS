<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_resolve_own_thread(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create(['user_id' => $student->id]);

        // Act
        $response = $this->actingAs($student)->post(route('qa-board.resolve', $thread));

        // Assert
        $response->assertRedirect(route('qa-board.show', $thread));
        $thread->refresh();
        $this->assertSame('resolved', $thread->status->value);
        $this->assertNotNull($thread->resolved_at);
    }

    public function test_other_user_cannot_resolve_thread(): void
    {
        // Arrange
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create(['user_id' => $owner->id]);

        // Act
        $response = $this->actingAs($other)->post(route('qa-board.resolve', $thread));

        // Assert
        $response->assertForbidden();
    }

    public function test_cannot_resolve_already_resolved_thread(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->resolved()->create(['user_id' => $student->id]);

        // Act
        $response = $this->actingAs($student)->post(route('qa-board.resolve', $thread));

        // Assert
        $response->assertForbidden();
    }

    public function test_owner_can_unresolve_resolved_thread(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->resolved()->create(['user_id' => $student->id]);

        // Act
        $response = $this->actingAs($student)->post(route('qa-board.unresolve', $thread));

        // Assert
        $response->assertRedirect(route('qa-board.show', $thread));
        $thread->refresh();
        $this->assertSame('unresolved', $thread->status->value);
        $this->assertNull($thread->resolved_at);
    }

    public function test_cannot_unresolve_already_unresolved_thread(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create(['user_id' => $student->id]);

        // Act
        $response = $this->actingAs($student)->post(route('qa-board.unresolve', $thread));

        // Assert
        $response->assertForbidden();
    }
}
