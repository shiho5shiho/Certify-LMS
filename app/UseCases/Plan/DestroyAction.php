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
 * NOTE: 判定は現在の User.plan_id の紐づきのみを見る。過去に user_plan_logs へ履歴が
 * 残っていても、現在の紐づきが無ければ削除可（PM確認済み・2026-09-27）。
 * DB側の user_plan_logs.plan_id には restrictOnDelete() が付いているため、
 * 万一「現在の紐づきは無いが過去履歴がある」プランを削除しようとした場合は
 * QueryException(23000) が発生しうる。本Actionでは事前チェックでは弾かず、
 * 発生時は素通りさせる（Laravelの例外ハンドラでDB例外として処理される想定）。
 *
 * @throws PlanNotDeletableException 下書き以外、または受講者が紐づいている場合
 */
final class DestroyAction
{
    public function __invoke(Plan $plan): void
    {
        if ($plan->status !== PlanStatus::Draft || $plan->users()->exists()) {
            throw new PlanNotDeletableException;
        }

        DB::transaction(fn () => $plan->delete());
    }
}
