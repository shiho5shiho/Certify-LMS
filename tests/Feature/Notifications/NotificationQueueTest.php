<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\AnnouncementTargetType;
use App\Mail\InvitationMail;
use App\Models\Announcement;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\Announcement\AdminAnnouncementNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * 通知・招待メールがその場で送られず、キューに積まれること、
 * リトライ回数と待機間隔が PM 確認どおりであることを検証する。
 */
class NotificationQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_announcement_queues_notifications_instead_of_sending_them_immediately(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->inProgress()->create();
        User::factory()->student()->inProgress()->count(2)->create();

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'メンテナンスのお知らせ',
            'body' => '10/20 2:00〜4:00 はご利用いただけません。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ]);

        $response->assertRedirect(route('admin.announcements.show', Announcement::sole()));
        // 受講生 2 人 × 2 チャネル(database / mail) = 4 ジョブ
        Queue::assertPushed(SendQueuedNotifications::class, 4);
        // worker が動くまでアプリ内通知は保存されない
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_business_event_notification_retries_three_times_with_increasing_backoff(): void
    {
        $notification = new AdminAnnouncementNotification(Announcement::factory()->create());

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertSame(4, $notification->tries);
        $this->assertSame([10, 60, 300], $notification->backoff());
    }

    public function test_invitation_mail_retries_three_times_with_increasing_backoff(): void
    {
        $mail = new InvitationMail(Invitation::factory()->pending()->create());

        $this->assertSame(4, $mail->tries);
        $this->assertSame([10, 60, 300], $mail->backoff());
    }
}
