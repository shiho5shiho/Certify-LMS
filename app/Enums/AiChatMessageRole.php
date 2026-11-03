<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * AI 相談メッセージの発言者を表す Enum。
 */
enum AiChatMessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';

    public function label(): string
    {
        return match ($this) {
            self::User => '受講生',
            self::Assistant => 'AI',
        };
    }
}
