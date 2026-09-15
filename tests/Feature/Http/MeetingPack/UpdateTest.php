<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_basic_info_without_changing_status(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->published()->create(['name' => '旧名称']);

        // Act
        $response = $this->actingAs($admin)->patch(route('admin.meeting-packs.update', $pack), [
            'name' => '新名称',
            'meeting_count' => $pack->meeting_count,
            'price' => $pack->price,
        ]);

        // Assert
        $response->assertRedirect(route('admin.meeting-packs.show', $pack));
        $this->assertSame('新名称', $pack->fresh()->name);
        $this->assertSame('published', $pack->fresh()->status->value);
    }

    public function test_coach_cannot_update(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $pack = MeetingPack::factory()->draft()->create();

        // Act
        $response = $this->actingAs($coach)->patch(route('admin.meeting-packs.update', $pack), [
            'name' => '名称',
            'meeting_count' => 1,
            'price' => 1000,
        ]);

        // Assert
        $response->assertForbidden();
    }
}
