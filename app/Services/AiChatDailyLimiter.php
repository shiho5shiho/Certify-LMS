<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AiChat\AiChatDailyLimitExceededException;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * AI 相談の 1 日の送信回数を数える(PM 確認 Q1)。
 *
 * - 上限は config: ai-chat.daily_limit。日付の区切りはアプリのタイムゾーン(Asia/Tokyo)の 0:00
 * - AI 応答に失敗した送信も数える。タイトル自動生成のための呼び出しは数えない
 * - 回数は会話とは別に記録するため、会話を削除しても減らない
 */
final class AiChatDailyLimiter
{
    /**
     * @throws AiChatDailyLimitExceededException
     */
    public function ensureNotExceeded(User $user): void
    {
        if (RateLimiter::tooManyAttempts($this->key($user), (int) config('ai-chat.daily_limit'))) {
            throw new AiChatDailyLimitExceededException;
        }
    }

    public function hit(User $user): void
    {
        // 当日の終わりまで保持する(キーに日付を含めるので、翌日は新しいカウントになる)
        RateLimiter::hit($this->key($user), now()->diffInSeconds(now()->endOfDay()) + 1);
    }

    private function key(User $user): string
    {
        return sprintf('ai-chat:daily:%s:%s', $user->id, now()->toDateString());
    }
}
