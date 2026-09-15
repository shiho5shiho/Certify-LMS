<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_meeting_pack_as_draft(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.store'), [
            'name' => '新規パック',
            'description' => '説明',
            'meeting_count' => 5,
            'price' => 12000,
        ]);

        // Assert
        $response->assertRedirect();
        $this->assertDatabaseHas('meeting_packs', [
            'name' => '新規パック',
            'status' => 'draft',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_coach_cannot_create_meeting_pack(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        // Act
        $response = $this->actingAs($coach)->post(route('admin.meeting-packs.store'), [
            'name' => '新規パック',
            'meeting_count' => 5,
            'price' => 12000,
        ]);

        // Assert
        $response->assertForbidden();
    }
}
