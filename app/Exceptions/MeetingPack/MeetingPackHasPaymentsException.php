<?php

declare(strict_types=1);

namespace App\Exceptions\MeetingPack;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 購入記録がある面談パックを削除しようとした際の例外（HTTP 409）。
 * `MeetingPack\DestroyAction` が「購入記録(決済ステータスを問わず)がある面談パックは削除不可」のドメインルールから throw する。
 * 削除すると、どのパックが購入されたかが分からなくなり、過去の購入を監査できなくなるため。
 * 販売を終了する場合はアーカイブで対応する。
 */
final class MeetingPackHasPaymentsException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('購入記録がある面談パックは削除できません。', $previous);
    }
}
