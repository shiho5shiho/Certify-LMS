<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use RuntimeException;
use Throwable;

/**
 * Gemini API の呼び出しに失敗した場合の例外(Gemini 側のエラー応答 / タイムアウト / 応答本文が空など)。
 *
 * upstreamStatus は Gemini が返した HTTP ステータス(接続できなかった場合は null)。
 * 呼び出し側は AI メッセージを error 状態で保存し、502 + upstream_status で応答する(画面の文言は JS が出し分ける)。
 */
final class GeminiRequestFailedException extends RuntimeException
{
    public function __construct(
        string $message = 'Gemini API の呼び出しに失敗しました。',
        public readonly ?int $upstreamStatus = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
