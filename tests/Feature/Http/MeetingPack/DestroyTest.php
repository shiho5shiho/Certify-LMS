<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_draft_meeting_pack(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->draft()->create();

        // Act
        $response = $this->actingAs($admin)->delete(route('admin.meeting-packs.destroy', $pack));

        // Assert
        $response->assertRedirect(route('admin.meeting-packs.index'));
        $this->assertDatabaseMissing('meeting_packs', ['id' => $pack->id]);
    }

    public function test_admin_can_delete_archived_meeting_pack(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->archived()->create();

        // Act
        $response = $this->actingAs($admin)->delete(route('admin.meeting-packs.destroy', $pack));

        // Assert
        $response->assertRedirect(route('admin.meeting-packs.index'));
        $this->assertDatabaseMissing('meeting_packs', ['id' => $pack->id]);
    }

    public function test_cannot_delete_published_meeting_pack(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->published()->create();

        // Act
        $response = $this->actingAs($admin)->deleteJson(route('admin.meeting-packs.destroy', $pack));

        // Assert
        $response->assertStatus(409);
        $this->assertDatabaseHas('meeting_packs', ['id' => $pack->id]);
    }

    public function test_coach_cannot_delete(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $pack = MeetingPack::factory()->draft()->create();

        // Act
        $response = $this->actingAs($coach)->delete(route('admin.meeting-packs.destroy', $pack));

        // Assert
        $response->assertForbidden();
    }
}
