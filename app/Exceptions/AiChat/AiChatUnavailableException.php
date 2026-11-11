<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Gemini の API キーが未設定のため、AI 相談を利用できない場合の例外。
 *
 * Gemini へのリクエストは行わない。JSON(ウィジェット / フル画面の送信)は 503、
 * 画面のフォーム送信(新しい相談モーダル)は直前の画面へ戻してトーストで案内する。
 */
final class AiChatUnavailableException extends HttpException
{
    public const MESSAGE = 'AI 相談は現在ご利用いただけません。';

    public function __construct()
    {
        parent::__construct(statusCode: 503, message: self::MESSAGE);
    }

    public function render(Request $request): ?RedirectResponse
    {
        if ($request->expectsJson()) {
            return null;
        }

        return redirect()->back()->with('error', $this->getMessage());
    }
}
