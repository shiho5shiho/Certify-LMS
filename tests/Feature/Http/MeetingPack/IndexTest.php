<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_meeting_pack_list(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->published()->count(3)->create();

        // Act
        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index'));

        // Assert
        $response->assertOk();
        $response->assertViewIs('meeting-pack.management.index');
        $response->assertViewHas('plans');
    }

    public function test_keyword_filter_matches_name_only(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->published()->create(['name' => '特別パック']);
        MeetingPack::factory()->published()->create(['name' => '通常パック']);

        // Act
        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index', ['keyword' => '特別']));

        // Assert
        $response->assertOk();
        $response->assertSee('特別パック');
        $response->assertDontSee('通常パック');
    }

    public function test_status_filter_returns_only_matching_status(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->draft()->create(['name' => 'Draft One']);
        MeetingPack::factory()->published()->create(['name' => 'Published One']);
        MeetingPack::factory()->archived()->create(['name' => 'Archived One']);

        // Act
        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index', ['status' => 'published']));

        // Assert
        $response->assertOk();
        $response->assertSee('Published One');
        $response->assertDontSee('Draft One');
        $response->assertDontSee('Archived One');
    }

    public function test_paginates_20_per_page(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        MeetingPack::factory()->published()->count(22)->create();

        // Act
        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.index'));

        // Assert
        $response->assertOk();
        $plans = $response->viewData('plans');
        $this->assertSame(20, $plans->perPage());
        $this->assertSame(22, $plans->total());
    }

    public function test_student_cannot_access(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act
        $response = $this->actingAs($student)->get(route('admin.meeting-packs.index'));

        // Assert
        $response->assertForbidden();
    }
}
