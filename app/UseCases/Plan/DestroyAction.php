<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanNotDeletableException;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * プランを物理削除するユースケース。下書き かつ 受講者が紐づいていない場合のみ削除可能。
 *
 * 受講者の判定は User.plan_id の紐づきで行い、退会済み(論理削除)の受講生も含める。
 * 利用履歴(user_plan_logs)が残っているプランも削除不可とする。
 * どちらも DB の外部キー(restrictOnDelete)で削除が拒否されるため、事前に判定してエラー画面にならないようにする。
 *
 * @throws PlanNotDeletableException 下書き以外、または受講者・利用履歴がある場合
 */
final class DestroyAction
{
    public function __invoke(Plan $plan): void
    {
        $hasUsers = $plan->users()->withTrashed()->exists();
        $hasLogs = $plan->userPlanLogs()->exists();

        if ($plan->status !== PlanStatus::Draft || $hasUsers || $hasLogs) {
            throw new PlanNotDeletableException;
        }

        DB::transaction(fn () => $plan->delete());
    }
}
