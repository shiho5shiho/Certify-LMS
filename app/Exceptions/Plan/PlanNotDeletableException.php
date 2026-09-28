<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * プラン削除不可の場合に throw される（下書き以外、または受講者が紐づいている場合）。
 * PM確認済み（2026-09-27）: 判定は現在の User.plan_id の紐づきのみを見る。
 * 過去に user_plan_logs へ履歴が残っていても、現在の紐づきが無ければ削除不可の判定には含めない。
 */
class PlanNotDeletableException extends ConflictHttpException
{
    public function __construct(
        string $message = 'このプランを削除できません。下書き かつ 受講者が紐づいていない場合のみ削除可能です。',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
