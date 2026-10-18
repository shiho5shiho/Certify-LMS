<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;

/**
 * 受講生メモ(EnrollmentNote)に対する認可ポリシー。
 *
 * - viewAny / create: 当該資格の担当コーチ / admin(受講生は本人であっても不可)
 * - update / delete: 作成者本人(かつ現在も担当コーチ) / admin
 * - 受講解除(論理削除)済みの受講登録では、閲覧・操作とも不可(メモのデータは保持する。PM 確認済)
 *
 * 担当コーチの判定は EnrollmentPolicy::view(certification.coaches 経由)を再利用する。
 */
class EnrollmentNotePolicy
{
    public function viewAny(User $user, Enrollment $enrollment): bool
    {
        return $this->canAccessEnrollment($user, $enrollment);
    }

    public function create(User $user, Enrollment $enrollment): bool
    {
        return $this->canAccessEnrollment($user, $enrollment);
    }

    public function update(User $user, EnrollmentNote $note): bool
    {
        return $this->canManage($user, $note);
    }

    public function delete(User $user, EnrollmentNote $note): bool
    {
        return $this->canManage($user, $note);
    }

    private function canManage(User $user, EnrollmentNote $note): bool
    {
        // 親の受講登録が解除(論理削除)済みなら null になるため、ここで拒否される
        $enrollment = $note->enrollment;

        if ($enrollment === null || ! $this->canAccessEnrollment($user, $enrollment)) {
            return false;
        }

        return $user->role === UserRole::Admin
            || $note->author_id === $user->id;
    }

    private function canAccessEnrollment(User $user, Enrollment $enrollment): bool
    {
        return $user->role !== UserRole::Student
            && ! $enrollment->trashed()
            && $user->can('view', $enrollment);
    }
}
