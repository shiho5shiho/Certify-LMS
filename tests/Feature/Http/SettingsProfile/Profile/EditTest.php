<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: callable}>
     */
    public static function userProvider(): array
    {
        return [
            '受講生' => [fn () => User::factory()->student()->create()],
            'コーチ' => [fn () => User::factory()->coach()->create()],
            '管理者' => [fn () => User::factory()->admin()->create()],
            '修了済受講生' => [fn () => User::factory()->student()->graduated()->create()],
        ];
    }

    /** @dataProvider userProvider */
    public function test_each_role_can_view_own_profile(callable $makeUser): void
    {
        $user = $makeUser();

        $this->actingAs($user)
            ->get(route('settings.profile.edit'))
            ->assertOk()
            ->assertViewIs('settings.profile')
            ->assertSee($user->email);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('settings.profile.edit'))->assertRedirect(route('login'));
    }

    public function test_meeting_url_field_is_shown_only_to_coach(): void
    {
        $this->actingAs(User::factory()->coach()->create())
            ->get(route('settings.profile.edit'))
            ->assertSee('name="meeting_url"', false);

        $this->actingAs(User::factory()->student()->create())
            ->get(route('settings.profile.edit'))
            ->assertDontSee('name="meeting_url"', false);
    }
}
