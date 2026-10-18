<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile\Avatar;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('settings.avatar.store'), [
                'avatar' => UploadedFile::fake()->image('me.png'),
            ])
            ->assertRedirect(route('settings.profile.edit'))
            ->assertSessionHas('success', 'アイコン画像を更新しました。');

        $url = $user->fresh()->avatar_url;
        $this->assertStringStartsWith('/storage/avatars/', $url);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $url));
    }

    public function test_old_avatar_file_is_deleted_when_replaced(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/old.png', 'dummy');
        $user = User::factory()->create(['avatar_url' => '/storage/avatars/old.png']);

        $this->actingAs($user)->post(route('settings.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('new.jpg'),
        ]);

        Storage::disk('public')->assertMissing('avatars/old.png');
    }

    public function test_rejects_unsupported_type(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('settings.avatar.store'), [
                'avatar' => UploadedFile::fake()->create('me.gif', 10, 'image/gif'),
            ])
            ->assertSessionHasErrors(['avatar']);

        $this->assertNull($user->fresh()->avatar_url);
    }

    public function test_rejects_file_over_2mb(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('settings.avatar.store'), [
                'avatar' => UploadedFile::fake()->image('big.png')->size(2049),
            ])
            ->assertSessionHasErrors(['avatar']);
    }
}
