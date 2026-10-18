<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile\Avatar;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_delete_avatar(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/me.png', 'dummy');
        $user = User::factory()->create(['avatar_url' => '/storage/avatars/me.png']);

        $this->actingAs($user)
            ->delete(route('settings.avatar.destroy'))
            ->assertRedirect(route('settings.profile.edit'))
            ->assertSessionHas('success', 'アイコン画像を削除しました。');

        $this->assertNull($user->fresh()->avatar_url);
        Storage::disk('public')->assertMissing('avatars/me.png');
    }
}
