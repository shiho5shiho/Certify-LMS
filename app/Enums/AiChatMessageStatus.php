<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * AI 相談メッセージの状態を表す Enum。
 *
 * AI 応答は Gemini 呼び出し前に pending で作成し、成功で completed、失敗で error に更新する。
 * 受講生の発言は常に completed。
 */
enum AiChatMessageStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '応答待ち',
            self::Completed => '完了',
            self::Error => 'エラー',
        };
    }
}
