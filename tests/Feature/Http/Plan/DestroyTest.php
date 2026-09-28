<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_draft_plan_without_users(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        // Act
        $response = $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan));

        // Assert
        $response->assertRedirect(route('admin.plans.index'));
        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }

    public function test_cannot_delete_published_plan(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        // Act
        $response = $this->actingAs($admin)->deleteJson(route('admin.plans.destroy', $plan));

        // Assert
        $response->assertStatus(409);
        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }

    public function test_cannot_delete_archived_plan(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create();

        // Act
        $response = $this->actingAs($admin)->deleteJson(route('admin.plans.destroy', $plan));

        // Assert
        $response->assertStatus(409);
        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }

    /**
     * Q1のPM回答: 判定は現在の User.plan_id の紐づきのみを見る。
     */
    public function test_cannot_delete_draft_plan_with_user_attached(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();
        User::factory()->student()->create(['plan_id' => $plan->id]);

        // Act
        $response = $this->actingAs($admin)->deleteJson(route('admin.plans.destroy', $plan));

        // Assert
        $response->assertStatus(409);
        $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    }

    public function test_coach_cannot_delete(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->draft()->create();

        // Act
        $response = $this->actingAs($coach)->delete(route('admin.plans.destroy', $plan));

        // Assert
        $response->assertForbidden();
    }
}
