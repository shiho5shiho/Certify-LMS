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
 * 通知 JSON API(Sanctum Cookie 認証)の検証。
 *
 * - 未ログインは 401
 * - 一覧は自分宛のみ・新着順・最新 20 件・未読タブで未読のみ、未読件数を返す
 * - 個別既読は自分宛のみ(他人宛は 403)で、遷移先 URL と未読件数を返す
 * - 全件既読は自分宛の未読のみを既読にする
 */
class NotificationApiTest extends TestCase
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

    public function test_guest_receives_401(): void
    {
        $this->getJson(route('api.v1.notifications.index'))->assertUnauthorized();
        $this->postJson(route('api.v1.notifications.markAllAsRead'))->assertUnauthorized();
    }

    public function test_index_returns_only_own_notifications_newest_first(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $old = $this->createNotification($user, createdAt: now()->subDay());
        $new = $this->createNotification($user, createdAt: now());
        $this->createNotification($other);

        $response = $this->actingAs($user)->getJson(route('api.v1.notifications.index'));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $new->id)
            ->assertJsonPath('data.1.id', $old->id)
            ->assertJsonPath('data.0.title', 'テスト通知')
            ->assertJsonPath('data.0.is_read', false)
            ->assertJsonPath('meta.unread_count', 2);
    }

    public function test_index_returns_latest_20_notifications(): void
    {
        $user = User::factory()->coach()->inProgress()->create();
        foreach (range(1, 21) as $minutes) {
            $this->createNotification($user, createdAt: now()->subMinutes($minutes));
        }

        $this->actingAs($user)->getJson(route('api.v1.notifications.index'))
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.unread_count', 21);
    }

    public function test_unread_tab_returns_only_unread_notifications(): void
    {
        $user = User::factory()->coach()->inProgress()->create();
        $unread = $this->createNotification($user);
        $this->createNotification($user, read: true);

        $this->actingAs($user)->getJson(route('api.v1.notifications.index', ['tab' => 'unread']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unread->id);
    }

    public function test_mark_as_read_marks_own_notification_and_returns_url(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($user);
        $this->createNotification($user);

        $this->actingAs($user)->postJson(route('api.v1.notifications.markAsRead', $notification))
            ->assertOk()
            ->assertJsonPath('data.url', route('dashboard.index'))
            ->assertJsonPath('meta.unread_count', 1);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_cannot_mark_others_notification_as_read(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $othersNotification = $this->createNotification($other);

        $this->actingAs($user)->postJson(route('api.v1.notifications.markAsRead', $othersNotification))
            ->assertForbidden();

        $this->assertNull($othersNotification->fresh()->read_at);
    }

    public function test_mark_all_as_read_marks_only_own_unread_notifications(): void
    {
        $user = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $mine = $this->createNotification($user);
        $others = $this->createNotification($other);

        $this->actingAs($user)->postJson(route('api.v1.notifications.markAllAsRead'))
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 0);

        $this->assertNotNull($mine->fresh()->read_at);
        $this->assertNull($others->fresh()->read_at);
    }
}
