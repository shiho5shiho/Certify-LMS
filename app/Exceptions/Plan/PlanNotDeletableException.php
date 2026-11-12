<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * プラン削除不可の場合に throw される（下書き以外、または受講者・利用履歴がある場合）。
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
