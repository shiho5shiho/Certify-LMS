<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
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
        $plan = Plan::factory()->published()->create(['name' => '旧名称']);

        // Act
        $response = $this->actingAs($admin)->patch(route('admin.plans.update', $plan), [
            'name' => '新名称',
            'duration_days' => $plan->duration_days,
            'default_meeting_quota' => $plan->default_meeting_quota,
        ]);

        // Assert
        $response->assertRedirect(route('admin.plans.show', $plan));
        $this->assertSame('新名称', $plan->fresh()->name);
        $this->assertSame('published', $plan->fresh()->status->value);
    }

    /**
     * Q2のPM回答: 公開中プランでも制限なく編集できる仕様であることを検証。
     */
    public function test_admin_can_update_published_plan_with_users_attached(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();
        User::factory()->student()->create(['plan_id' => $plan->id]);

        // Act
        $response = $this->actingAs($admin)->patch(route('admin.plans.update', $plan), [
            'name' => $plan->name,
            'duration_days' => 60,
            'default_meeting_quota' => 8,
        ]);

        // Assert
        $response->assertRedirect(route('admin.plans.show', $plan));
        $this->assertSame(60, $plan->fresh()->duration_days);
        $this->assertSame(8, $plan->fresh()->default_meeting_quota);
    }

    public function test_coach_cannot_update(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $plan = Plan::factory()->draft()->create();

        // Act
        $response = $this->actingAs($coach)->patch(route('admin.plans.update', $plan), [
            'name' => '名称',
            'duration_days' => 30,
            'default_meeting_quota' => 4,
        ]);

        // Assert
        $response->assertForbidden();
    }
}
