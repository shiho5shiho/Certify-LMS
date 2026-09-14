<?php

declare(strict_types=1);

namespace App\Exceptions\MeetingPack;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 削除条件を満たさない面談パックを削除しようとした際の例外（HTTP 409）。
 * `MeetingPack\DestroyAction` が「公開中でなければ削除可」のドメインルールから throw する。
 *
 * NOTE: 「購入履歴があっても公開中でなければ削除可としてよいか」はPM確認中（回答待ち）。
 * NGの回答が来た場合、DestroyAction側の削除条件（と、このメッセージ）を修正する。
 */
final class MeetingPackNotDeletableException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('公開中でない面談パックのみ削除できます。', $previous);
    }
}
