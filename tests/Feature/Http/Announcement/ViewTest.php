<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 配信履歴の一覧・詳細・配信フォームが管理者だけに表示されることを検証する。
 */
class ViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_index_create_and_show(): void
    {
        $admin = User::factory()->admin()->inProgress()->create();
        $announcement = Announcement::factory()->create([
            'title' => '年末年始の運営休止について',
            'body' => '12/29〜1/3 はお休みします。',
            'dispatched_count' => 3,
        ]);

        $this->actingAs($admin)->get(route('admin.announcements.index'))
            ->assertOk()
            ->assertSee('年末年始の運営休止について');

        $this->actingAs($admin)->get(route('admin.announcements.create'))
            ->assertOk();

        $this->actingAs($admin)->get(route('admin.announcements.show', $announcement))
            ->assertOk()
            ->assertSee('12/29〜1/3 はお休みします。')
            ->assertSee('3 件');
    }

    public function test_non_admin_is_forbidden(): void
    {
        $announcement = Announcement::factory()->create();

        foreach ([User::factory()->coach()->inProgress()->create(), User::factory()->student()->inProgress()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.announcements.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.announcements.create'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.announcements.show', $announcement))->assertForbidden();
        }
    }
}
