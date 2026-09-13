<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Models\QaThread;
use App\Models\User;

/**
 * 質問掲示板スレッドの認可ルール。
 *
 * - admin: 閲覧・削除(モデレーション)のみ。編集・解決マーク代行は不可
 * - coach: 担当資格(公開中のみ)のスレッドを閲覧可。投稿・編集・削除は不可
 * - student: 公開中の資格すべてに投稿可(Enrollment の有無は問わない)。
 *   自分のスレッドのみ編集・解決切替・削除可
 */
class QaThreadPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Coach, UserRole::Student], true);
    }

    public function view(User $user, QaThread $thread): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if (! $this->isCertificationPublished($thread)) {
            return false;
        }

        return match ($user->role) {
            UserRole::Coach => $this->isAssignedCoach($user, $thread),
            UserRole::Student => true,
            default => false,
        };
    }

    /**
     * 投稿は受講生のみ。資格ごとの可否は StoreRequest 側(公開中の資格か)で検証する。
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Student;
    }

    public function update(User $user, QaThread $thread): bool
    {
        return $user->role === UserRole::Student && $thread->user_id === $user->id;
    }

    public function delete(User $user, QaThread $thread): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $this->isOwner($user, $thread) && $this->canDeleteByOwner($thread);
    }

    public function resolve(User $user, QaThread $thread): bool
    {
        return $this->isOwner($user, $thread) && $thread->status === QaThreadStatus::Unresolved;
    }

    public function unresolve(User $user, QaThread $thread): bool
    {
        return $this->isOwner($user, $thread) && $thread->status === QaThreadStatus::Resolved;
    }

    private function isOwner(User $user, QaThread $thread): bool
    {
        return $user->role === UserRole::Student && $thread->user_id === $user->id;
    }

    private function canDeleteByOwner(QaThread $thread): bool
    {
        return $thread->replies()->count() === 0;
    }

    private function isAssignedCoach(User $coach, QaThread $thread): bool
    {
        $thread->loadMissing('certification.coaches');

        return $thread->certification->coaches->contains('id', $coach->id);
    }

    private function isCertificationPublished(QaThread $thread): bool
    {
        $thread->loadMissing('certification');

        return $thread->certification?->status === CertificationStatus::Published;
    }
}
