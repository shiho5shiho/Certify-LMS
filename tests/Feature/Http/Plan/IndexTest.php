<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_plan_list(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        Plan::factory()->published()->count(3)->create();

        // Act
        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

        // Assert
        $response->assertOk();
        $response->assertViewIs('plan.management.index');
        $response->assertViewHas('plans');
    }

    public function test_keyword_filter_matches_name_only(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        Plan::factory()->published()->create(['name' => '特別プラン']);
        Plan::factory()->published()->create(['name' => '通常プラン']);

        // Act
        $response = $this->actingAs($admin)->get(route('admin.plans.index', ['keyword' => '特別']));

        // Assert
        $response->assertOk();
        $response->assertSee('特別プラン');
        $response->assertDontSee('通常プラン');
    }

    public function test_status_filter_returns_only_matching_status(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        Plan::factory()->draft()->create(['name' => 'Draft One']);
        Plan::factory()->published()->create(['name' => 'Published One']);
        Plan::factory()->archived()->create(['name' => 'Archived One']);

        // Act
        $response = $this->actingAs($admin)->get(route('admin.plans.index', ['status' => 'published']));

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
        Plan::factory()->published()->count(22)->create();

        // Act
        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

        // Assert
        $response->assertOk();
        $plans = $response->viewData('plans');
        $this->assertSame(20, $plans->perPage());
        $this->assertSame(22, $plans->total());
    }

    /**
     * Q4のPM回答: 公開中 → 下書き → アーカイブの優先順で並ぶことを検証。
     */
    public function test_ordered_by_status_priority_then_sort_order(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $archived = Plan::factory()->archived()->create(['name' => 'アーカイブ分', 'sort_order' => 0]);
        $draft = Plan::factory()->draft()->create(['name' => '下書き分', 'sort_order' => 0]);
        $published = Plan::factory()->published()->create(['name' => '公開分', 'sort_order' => 0]);

        // Act
        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

        // Assert
        $plans = $response->viewData('plans');
        $ids = $plans->pluck('id')->all();
        $this->assertSame(
            [$published->id, $draft->id, $archived->id],
            $ids,
        );
    }

    public function test_student_cannot_access(): void
    {
        // Arrange
        $student = User::factory()->student()->create();

        // Act
        $response = $this->actingAs($student)->get(route('admin.plans.index'));

        // Assert
        $response->assertForbidden();
    }
}
