<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Models\AiChatConversation;

/**
 * AI 相談の会話タイトルの手動編集。
 *
 * 受講生が編集した会話は、以降 AI によるタイトルの自動付け直しを行わない(手動で付けたタイトルを優先)。
 */
final class UpdateAction
{
    /**
     * @param array{title: string} $data
     */
    public function __invoke(AiChatConversation $conversation, array $data): AiChatConversation
    {
        $conversation->update([
            'title' => $data['title'],
            'title_edited_by_user' => true,
        ]);

        return $conversation;
    }
}
