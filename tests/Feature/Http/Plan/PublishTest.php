<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_publishes_draft_plan(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.plans.publish', $plan));

        // Assert
        $response->assertRedirect(route('admin.plans.show', $plan));
        $this->assertSame('published', $plan->fresh()->status->value);
    }

    public function test_cannot_publish_archived(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.plans.publish', $plan));

        // Assert
        $response->assertStatus(409);
    }

    public function test_cannot_publish_already_published(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.plans.publish', $plan));

        // Assert
        $response->assertStatus(409);
    }

    public function test_coach_cannot_publish(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->draft()->create();

        // Act
        $response = $this->actingAs($coach)->post(route('admin.plans.publish', $plan));

        // Assert
        $response->assertForbidden();
    }
}
