<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * プランの公開状態遷移（publish / archive / unarchive）が不正な開始状態から呼ばれた際の例外（HTTP 409）。
 * 許可される遷移は 下書き→公開中→アーカイブ→下書き の一方向巡回のみ。
 * バリエーションごとに static factory（`forPublish` / `forArchive` / `forUnarchive`）でメッセージを生成する。
 */
final class PlanInvalidTransitionException extends ConflictHttpException
{
    public static function forPublish(): self
    {
        return new self('下書き状態のプランのみ公開できます。');
    }

    public static function forArchive(): self
    {
        return new self('公開中のプランのみアーカイブできます。');
    }

    public static function forUnarchive(): self
    {
        return new self('アーカイブ済みのプランのみ下書きへ戻せます。');
    }

    private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
