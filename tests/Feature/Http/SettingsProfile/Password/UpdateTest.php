<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile\Password;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('settings.password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect(route('settings.profile.edit', ['tab' => 'password']))
            ->assertSessionHas('success', 'パスワードを変更しました。');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_fails_when_current_password_is_wrong(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('settings.profile.edit', ['tab' => 'password']))
            ->put(route('settings.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect(route('settings.profile.edit', ['tab' => 'password']))
            ->assertSessionHasErrorsIn('updatePassword', [
                'current_password' => '現在のパスワードが正しくありません。',
            ]);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_fails_when_confirmation_does_not_match(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('settings.password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'different',
            ])
            ->assertSessionHasErrorsIn('updatePassword', ['password']);
    }
}
