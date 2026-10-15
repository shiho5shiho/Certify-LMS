<?php

declare(strict_types=1);

namespace App\UseCases\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Exceptions\Enrollment\EnrollmentInvalidTransitionException;
use App\Models\Enrollment;
use App\Services\DefaultEnrollmentService;
use Illuminate\Support\Facades\DB;

/**
 * 受講生による受講解除(SoftDelete) Action。learning 状態の Enrollment のみ削除可。
 * passed / failed は履歴として残すため拒否する。
 *
 * 当該 Enrollment が受講生のデフォルト資格だった場合は、他の learning|passed 残存件数で自動振替 / NULL リセット。
 *
 * 配下の個人目標は物理削除する(要件: 親の受講登録が削除された場合、配下の目標も連動して削除)。
 */
final class DestroyAction
{
    public function __construct(
        private readonly DefaultEnrollmentService $defaultEnrollmentService,
    ) {}

    /**
     * @throws EnrollmentInvalidTransitionException
     */
    public function __invoke(Enrollment $enrollment): void
    {
        if ($enrollment->status !== EnrollmentStatus::Learning) {
            throw EnrollmentInvalidTransitionException::forDestroy();
        }

        DB::transaction(function () use ($enrollment) {
            $user = $enrollment->user;

            // 受講解除(論理削除)では外部キーの cascadeOnDelete が働かないため、配下の個人目標を明示的に物理削除する
            $enrollment->goals()->delete();

            $enrollment->delete();

            $this->defaultEnrollmentService->resolveAfterStatusChange($user, $enrollment);
        });
    }
}
