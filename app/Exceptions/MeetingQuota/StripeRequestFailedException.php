<?php

declare(strict_types=1);

namespace App\Exceptions\MeetingQuota;

use RuntimeException;
use Throwable;

/**
 * Stripe API との通信に失敗した場合の例外(キー未設定 / 障害 / タイムアウト / 想定外の応答など)。
 *
 * 呼び出し側で画面にエラーを表示して処理を中断する。HTTP 例外にはしない。
 */
final class StripeRequestFailedException extends RuntimeException
{
    public function __construct(string $message = 'Stripe API の呼び出しに失敗しました。', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
