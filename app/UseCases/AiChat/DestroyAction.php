<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Models\AiChatConversation;

/**
 * AI 相談の会話の削除。配下のメッセージは外部キーの cascadeOnDelete で連動削除される。
 * 日次上限の回数は会話とは別に記録しているため、削除しても減らない。
 */
final class DestroyAction
{
    public function __invoke(AiChatConversation $conversation): void
    {
        $conversation->delete();
    }
}
