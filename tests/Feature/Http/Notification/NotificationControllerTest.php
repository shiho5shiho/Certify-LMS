<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 通知の一覧・個別既読・一括既読の検証。
 *
 * - 一覧は自分宛のみ・新着順・1 ページ 20 件・未読タブで未読のみ
 * - タブ切替で範囲外ページになった場合は同じタブの 1 ページ目へ戻す
 * - 個別既読は自分宛のみ(他人宛は 403)で、通知の url へ移動する
 * - 一括既読は自分宛の未読のみを既読にする
 */
class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createNotification(User $user, bool $read = false, ?Carbon $createdAt = null): DatabaseNotification
    {
        $createdAt ??= now();

        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'data' => [
                'notification_type' => 'chat_message_received',
                'title' => 'テスト通知',
                'message' => 'テスト本文',
                'url' => route('dashboard.index'),
            ],
            'read_at' => $read ? $createdAt : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_only_own_notifications_newest_first(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $old = $this->createNotification($user, createdAt: now()->subDay());
        $new = $this->createNotification($user, createdAt: now());
        $othersNotification = $this->createNotification($other);

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertOk();
        $response->assertViewIs('notifications.index');
        $response->assertViewHas('notifications', fn ($notifications) => $notifications->pluck('id')->all() === [$new->id, $old->id]
            && ! $notifications->contains('id', $othersNotification->id));
        $response->assertViewHas('unreadCount', 2);
        $response->assertViewHas('tab', 'all');
    }

    public function test_unread_tab_shows_only_unread_notifications(): void
    {
        $user = User::factory()->coach()->inProgress()->create();
        $unread = $this->createNotification($user);
        $read = $this->createNotification($user, read: true);

        $response = $this->actingAs($user)->get(route('notifications.index', ['tab' => 'unread']));

        $response->assertOk();
        $response->assertViewHas('notifications', fn ($notifications) => $notifications->contains('id', $unread->id)
            && ! $notifications->contains('id', $read->id));
        $response->assertViewHas('tab', 'unread');
    }

    public function test_index_paginates_20_per_page(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        for ($i = 0; $i < 21; $i++) {
            $this->createNotification($user, createdAt: now()->subMinutes($i));
        }

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertViewHas('notifications', fn ($notifications) => $notifications->count() === 20
            && $notifications->total() === 21);
    }

    public function test_out_of_range_page_redirects_to_first_page_of_same_tab(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $this->createNotification($user);

        $response = $this->actingAs($user)->get(route('notifications.index', ['tab' => 'unread', 'page' => 2]));

        $response->assertRedirect(route('notifications.index', ['tab' => 'unread']));
    }

    public function test_invalid_tab_is_rejected(): void
    {
        $user = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($user)->get(route('notifications.index', ['tab' => 'invalid']));

        $response->assertSessionHasErrors('tab');
    }

    public function test_mark_as_read_marks_own_notification_and_redirects_to_url(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($user);

        $response = $this->actingAs($user)->post(route('notifications.markAsRead', $notification));

        $response->assertRedirect(route('dashboard.index'));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_as_read_is_forbidden_for_others_notification(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($owner);

        $response = $this->actingAs($other)->post(route('notifications.markAsRead', $notification));

        $response->assertForbidden();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read_marks_only_own_unread_notifications(): void
    {
        $user = User::factory()->coach()->inProgress()->create();
        $other = User::factory()->coach()->inProgress()->create();
        $this->createNotification($user);
        $this->createNotification($user);
        $othersNotification = $this->createNotification($other);

        $response = $this->actingAs($user)->post(route('notifications.markAllAsRead'));

        $response->assertRedirect(route('notifications.index'));
        $response->assertSessionHas('success', 'すべての通知を既読にしました。');
        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertNull($othersNotification->fresh()->read_at);
    }

    public function test_show_displays_own_notification_and_marks_it_as_read(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($user);

        $this->actingAs($user)
            ->get(route('notifications.show', $notification))
            ->assertOk()
            ->assertSee('テスト通知')
            ->assertSee('テスト本文');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_show_is_forbidden_for_others_notification(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($owner);

        $this->actingAs($other)
            ->get(route('notifications.show', $notification))
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }
}
