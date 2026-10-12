<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * 通知(DatabaseNotification)に対する認可ポリシー。
 *
 * - markAsRead: 自分宛の通知のみ既読化できる(他人宛は 403)
 */
class NotificationPolicy
{
    public function markAsRead(User $user, DatabaseNotification $notification): bool
    {
        return $notification->notifiable_type === $user->getMorphClass()
            && $notification->notifiable_id === $user->id;
    }
}
