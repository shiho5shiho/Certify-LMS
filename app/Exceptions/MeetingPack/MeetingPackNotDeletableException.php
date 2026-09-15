<?php

declare(strict_types=1);

namespace App\Exceptions\MeetingPack;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 削除条件を満たさない面談パックを削除しようとした際の例外（HTTP 409）。
 * `MeetingPack\DestroyAction` が「公開中でなければ削除可」のドメインルールから throw する。
 *
 * NOTE: 現状スキーマには購入履歴を表すテーブル(payments等)が存在せず、meeting_pack_id への外部キー参照も無いため、
 * 「公開中でなければ常に削除可」で問題ないとPM確認済み（2026-09-15）。
 * S-A-03（Stripe連携）で購入履歴テーブルが追加された際は、物理削除のままでよいか
 * （外部キー制約 or カスケード削除の扱い）を改めて設計し直す必要がある。
 */
final class MeetingPackNotDeletableException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('公開中でない面談パックのみ削除できます。', $previous);
    }
}
