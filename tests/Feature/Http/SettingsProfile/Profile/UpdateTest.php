<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_name_and_bio_but_not_email(): void
    {
        $user = User::factory()->student()->create(['email' => 'before@example.com']);

        $this->actingAs($user)
            ->patch(route('settings.profile.update'), [
                'name' => '新しい名前',
                'bio' => 'よろしくお願いします',
                'email' => 'after@example.com',
            ])
            ->assertRedirect(route('settings.profile.edit'))
            ->assertSessionHas('success', 'プロフィールを更新しました。');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => '新しい名前',
            'bio' => 'よろしくお願いします',
            'email' => 'before@example.com',
        ]);
    }

    public function test_graduated_student_can_update_profile(): void
    {
        $user = User::factory()->student()->graduated()->create();

        $this->actingAs($user)
            ->patch(route('settings.profile.update'), ['name' => '修了生'])
            ->assertRedirect(route('settings.profile.edit'));

        $this->assertSame('修了生', $user->fresh()->name);
    }

    public function test_coach_can_update_meeting_url(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)->patch(route('settings.profile.update'), [
            'name' => $coach->name,
            'meeting_url' => 'https://zoom.us/j/123456789',
        ]);

        $this->assertSame('https://zoom.us/j/123456789', $coach->fresh()->meeting_url);
    }

    public function test_non_coach_cannot_update_meeting_url(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->patch(route('settings.profile.update'), [
            'name' => $student->name,
            'meeting_url' => 'https://zoom.us/j/123456789',
        ]);

        $this->assertNull($student->fresh()->meeting_url);
    }

    public function test_validation_fails_with_invalid_values(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)
            ->from(route('settings.profile.edit'))
            ->patch(route('settings.profile.update'), [
                'name' => str_repeat('あ', 51),
                'bio' => str_repeat('あ', 1001),
                'meeting_url' => 'not-a-url',
            ])
            ->assertRedirect(route('settings.profile.edit'))
            ->assertSessionHasErrors(['name', 'bio', 'meeting_url']);
    }

    public function test_coach_meeting_url_is_required(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)
            ->patch(route('settings.profile.update'), ['name' => $coach->name, 'meeting_url' => ''])
            ->assertSessionHasErrors(['meeting_url' => '固定面談 URL を入力してください。']);
    }
}
