<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Models\User;

/**
 * 自分宛の未読通知をまとめて既読にするユースケース。既読にした件数を返す。
 */
final class MarkAllAsReadAction
{
    public function __invoke(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
