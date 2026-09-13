<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_post_thread_even_without_enrollment(): void
    {
        // Arrange: Enrollment を一切作らず、公開中の資格だけを用意する
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        // Act
        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => 'テストタイトル',
            'body' => 'テスト本文',
        ]);

        // Assert
        $response->assertRedirect();
        $this->assertDatabaseHas('qa_threads', [
            'certification_id' => $certification->id,
            'user_id' => $student->id,
            'title' => 'テストタイトル',
            'status' => 'unresolved',
        ]);
    }

    public function test_coach_cannot_post_thread(): void
    {
        // Arrange
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        // Act
        $response = $this->actingAs($coach)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => 'テストタイトル',
            'body' => 'テスト本文',
        ]);

        // Assert
        $response->assertForbidden();
    }

    public function test_cannot_post_thread_for_unpublished_certification(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $draft = Certification::factory()->draft()->create();

        // Act
        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $draft->id,
            'title' => 'テストタイトル',
            'body' => 'テスト本文',
        ]);

        // Assert
        $response->assertSessionHasErrors('certification_id');
    }

    public function test_title_over_200_characters_is_rejected(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        // Act
        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => str_repeat('あ', 201),
            'body' => 'テスト本文',
        ]);

        // Assert
        $response->assertSessionHasErrors('title');
    }

    public function test_missing_body_is_rejected(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();

        // Act
        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => 'テストタイトル',
            'body' => '',
        ]);

        // Assert
        $response->assertSessionHasErrors('body');
    }
}
