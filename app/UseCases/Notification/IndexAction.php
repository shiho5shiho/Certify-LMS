<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 自分宛の通知一覧を新しい順・1 ページ 20 件で返すユースケース。
 * tab=unread のときは未読のみに絞る。
 */
final class IndexAction
{
    public function __invoke(User $user, string $tab): LengthAwarePaginator
    {
        $query = $tab === 'unread'
            ? $user->unreadNotifications()
            : $user->notifications();

        return $query->paginate(20)->withQueryString();
    }
}
