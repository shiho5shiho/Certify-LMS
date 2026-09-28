<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_archives_published_plan(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.plans.archive', $plan));

        // Assert
        $response->assertRedirect(route('admin.plans.show', $plan));
        $this->assertSame('archived', $plan->fresh()->status->value);
    }

    public function test_cannot_archive_draft(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.plans.archive', $plan));

        // Assert
        $response->assertStatus(409);
    }

    public function test_cannot_archive_already_archived(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.plans.archive', $plan));

        // Assert
        $response->assertStatus(409);
    }

    public function test_coach_cannot_archive(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->published()->create();

        // Act
        $response = $this->actingAs($coach)->post(route('admin.plans.archive', $plan));

        // Assert
        $response->assertForbidden();
    }
}
