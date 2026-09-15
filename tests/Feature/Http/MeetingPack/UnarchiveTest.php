<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnarchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_unarchives_archived_meeting_pack_to_draft(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->archived()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.unarchive', $pack));

        // Assert
        $response->assertRedirect(route('admin.meeting-packs.show', $pack));
        $this->assertSame('draft', $pack->fresh()->status->value);
    }

    public function test_cannot_unarchive_draft(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->draft()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.meeting-packs.unarchive', $pack));

        // Assert
        $response->assertStatus(409);
    }

    public function test_cannot_unarchive_published(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->published()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.meeting-packs.unarchive', $pack));

        // Assert
        $response->assertStatus(409);
    }

    public function test_coach_cannot_unarchive(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $pack = MeetingPack::factory()->archived()->create();

        // Act
        $response = $this->actingAs($coach)->post(route('admin.meeting-packs.unarchive', $pack));

        // Assert
        $response->assertForbidden();
    }
}
