<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_plan_as_draft(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => '新規プラン',
            'description' => '説明',
            'duration_days' => 30,
            'default_meeting_quota' => 4,
        ]);

        // Assert
        $response->assertRedirect();
        $this->assertDatabaseHas('plans', [
            'name' => '新規プラン',
            'status' => 'draft',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_duration_days_must_be_within_range(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => '新規プラン',
            'duration_days' => 3651,
            'default_meeting_quota' => 4,
        ]);

        // Assert
        $response->assertSessionHasErrors('duration_days');
    }

    public function test_default_meeting_quota_must_be_within_range(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => '新規プラン',
            'duration_days' => 30,
            'default_meeting_quota' => 1001,
        ]);

        // Assert
        $response->assertSessionHasErrors('default_meeting_quota');
    }

    public function test_coach_cannot_create_plan(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        // Act
        $response = $this->actingAs($coach)->post(route('admin.plans.store'), [
            'name' => '新規プラン',
            'duration_days' => 30,
            'default_meeting_quota' => 4,
        ]);

        // Assert
        $response->assertForbidden();
    }
}
