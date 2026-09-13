<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

/**
 * 質問掲示板の回答に対する認可ルール。
 *
 * - admin: 投稿不可。モデレーション削除のみ可
 * - coach: 担当資格(公開中)のスレッドに回答可。自分の回答のみ編集・削除可
 * - student: 閲覧可能なスレッド(QaThreadPolicy::view)に回答可。自分の回答のみ編集・削除可
 */
class QaReplyPolicy
{
    public function __construct(private readonly QaThreadPolicy $threadPolicy) {}

    public function create(User $user, QaThread $thread): bool
    {
        if ($user->role === UserRole::Admin) {
            return false;
        }

        return $this->threadPolicy->view($user, $thread);
    }

    public function update(User $user, QaReply $reply): bool
    {
        return $reply->user_id === $user->id && $user->role !== UserRole::Admin;
    }

    public function delete(User $user, QaReply $reply): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $reply->user_id === $user->id;
    }
}
