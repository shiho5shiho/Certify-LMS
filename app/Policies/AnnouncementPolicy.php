<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\User;

/**
 * 管理者お知らせの認可ルール。全アクション管理者(admin)のみ許可する。
 * 編集・削除・再配信は要件で不可のため、update / delete は定義しない。
 */
class AnnouncementPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function view(User $auth, Announcement $announcement): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function create(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }
}
