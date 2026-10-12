<?php

declare(strict_types=1);

namespace App\Notifications\QaBoard;

use App\Models\QaReply;
use App\Notifications\BusinessEventNotification;

/**
 * 質問への回答通知。質問の投稿者(受講生)に送る。
 */
final class QaReplyReceivedNotification extends BusinessEventNotification
{
    public function __construct(private readonly QaReply $reply) {}

    protected function notificationType(): string
    {
        return 'qa_reply_received';
    }

    protected function title(): string
    {
        return "{$this->reply->user->name}さんがあなたの質問に回答しました";
    }

    protected function message(): string
    {
        return $this->reply->thread->title;
    }

    protected function url(): string
    {
        return route('qa-board.show', $this->reply->qa_thread_id).'#reply-'.$this->reply->id;
    }
}
