<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;

/**
 * 個人学習目標(EnrollmentGoal)に対する認可ポリシー。
 *
 * - create / update / delete / markAchieved / unmarkAchieved: 受講生本人のみ
 * - 閲覧は受講登録詳細画面の EnrollmentPolicy::view(本人 / 担当コーチ / admin)に委ねる
 *
 * 受講解除(論理削除)済の受講登録には目標を追加させない(解除時に配下の目標は削除されるため)。
 */
class EnrollmentGoalPolicy
{
    public function create(User $user, Enrollment $enrollment): bool
    {
        return $this->isOwner($user, $enrollment)
            && ! $enrollment->trashed();
    }

    public function update(User $user, EnrollmentGoal $goal): bool
    {
        return $this->isOwner($user, $goal->enrollment);
    }

    public function delete(User $user, EnrollmentGoal $goal): bool
    {
        return $this->isOwner($user, $goal->enrollment);
    }

    public function markAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $this->isOwner($user, $goal->enrollment);
    }

    public function unmarkAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $this->isOwner($user, $goal->enrollment);
    }

    private function isOwner(User $user, ?Enrollment $enrollment): bool
    {
        return $user->role === UserRole::Student
            && $enrollment !== null
            && $enrollment->user_id === $user->id;
    }
}
