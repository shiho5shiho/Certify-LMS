<?php

declare(strict_types=1);

namespace App\Exceptions\Mentoring;

use RuntimeException;
use Throwable;

/**
 * Google Calendar API との通信が一時的に失敗した場合の例外(障害 / タイムアウト / 想定外の応答など)。
 *
 * 連携情報は残したまま、呼び出し側でフォールバックする(空き枠はその日を予約不可、予定の登録 / 削除はログのみ)。
 * 画面には出さないため HTTP 例外にはしない。
 */
final class GoogleCalendarRequestFailedException extends RuntimeException
{
    public function __construct(string $message = 'Google Calendar API の呼び出しに失敗しました。', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
