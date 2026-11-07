<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications\Announcement;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\Announcement\AdminAnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 管理者お知らせのメール本文が、改行ごとに 1 行ずつ、文字化けせずに載ることを検証する。
 */
class AdminAnnouncementNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_body_keeps_multibyte_characters_and_splits_only_at_line_breaks(): void
    {
        $announcement = Announcement::factory()->create([
            'title' => 'お知らせ',
            'body' => "失敗ジョブの記録と再投入\n2 行目",
        ]);
        $notification = new AdminAnnouncementNotification($announcement);
        $notification->id = (string) Str::uuid();

        $mail = $notification->toMail(User::factory()->student()->create());

        $this->assertSame(['お知らせ', '失敗ジョブの記録と再投入', '2 行目'], $mail->introLines);
    }
}
