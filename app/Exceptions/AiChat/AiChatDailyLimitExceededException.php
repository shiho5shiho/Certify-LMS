<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * 受講生 1 人あたりの 1 日の送信上限(config: ai-chat.daily_limit)を超えた場合の例外。
 *
 * JSON は 429(画面の文言は JS が出す)、画面のフォーム送信は直前の画面へ戻してトーストで案内する。
 * 文言は提供済み JS(full-screen.js / floating-widget.js の describeError)と同じにする。
 */
final class AiChatDailyLimitExceededException extends HttpException
{
    public function __construct()
    {
        parent::__construct(
            statusCode: 429,
            message: '本日の利用上限に達しました。明日 0:00 以降に再度ご利用ください。',
        );
    }

    public function render(Request $request): ?RedirectResponse
    {
        if ($request->expectsJson()) {
            return null;
        }

        return redirect()->back()->with('error', $this->getMessage());
    }
}
