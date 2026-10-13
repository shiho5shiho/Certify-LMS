<?php

declare(strict_types=1);

namespace App\Notifications\QaBoard;

use App\Models\QaThread;
use App\Notifications\BusinessEventNotification;

/**
 * 質問の新規投稿通知。その資格の有効な担当コーチ全員に送る。
 */
final class QaThreadPostedNotification extends BusinessEventNotification
{
    public function __construct(private readonly QaThread $thread) {}

    protected function notificationType(): string
    {
        return 'qa_thread_posted';
    }

    protected function title(): string
    {
        return "{$this->thread->user->name}さんが質問を投稿しました";
    }

    protected function message(): string
    {
        return $this->thread->title;
    }

    protected function url(): string
    {
        return route('qa-board.show', $this->thread);
    }
}
