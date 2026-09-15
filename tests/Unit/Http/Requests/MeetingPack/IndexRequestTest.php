<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\MeetingPack;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_index(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index'));

        // Assert
        $response->assertOk();
    }

    public function test_invalid_status_value_returns_422(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->getJson(route('admin.meeting-packs.index', ['status' => 'unknown']));

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('status');
    }

    public function test_coach_cannot_access_index(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        // Act
        $response = $this->actingAs($coach)->get(route('admin.meeting-packs.index'));

        // Assert
        $response->assertForbidden();
    }
}
