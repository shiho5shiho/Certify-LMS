<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use Illuminate\Notifications\DatabaseNotification;

/**
 * 通知 1 件を既読にするユースケース。既読済みの場合は read_at を更新しない。
 */
final class MarkAsReadAction
{
    public function __invoke(DatabaseNotification $notification): void
    {
        $notification->markAsRead();
    }
}
