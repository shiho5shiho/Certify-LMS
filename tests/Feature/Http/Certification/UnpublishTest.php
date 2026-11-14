<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Certification;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnpublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_unpublishes_published_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($admin)->post(route('admin.certifications.unpublish', $cert));

        $response->assertRedirect(route('admin.certifications.show', $cert));
        $this->assertSame('draft', $cert->fresh()->status->value);
    }

    public function test_cannot_unpublish_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->draft()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.certifications.unpublish', $cert));

        $response->assertStatus(409);
    }

    public function test_cannot_unpublish_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->archived()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.certifications.unpublish', $cert));

        $response->assertStatus(409);
    }

    public function test_coach_cannot_unpublish(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($coach)->post(route('admin.certifications.unpublish', $cert));

        $response->assertForbidden();
    }

    public function test_show_page_displays_unpublish_confirm_description(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();

        $this->actingAs($admin)
            ->get(route('admin.certifications.show', $cert))
            ->assertOk()
            ->assertSee('既存受講生の受講登録と学習記録は保持されますが、公開停止中は教材を閲覧できません。')
            ->assertDontSee('既存受講生の学習継続には影響しません');
    }
}
