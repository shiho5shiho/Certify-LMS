<?php

declare(strict_types=1);

namespace App\Notifications\Chat;

use App\Models\ChatMessage;
use App\Notifications\BusinessEventNotification;
use Illuminate\Support\Str;

/**
 * チャットの新着メッセージ通知。送信者以外のルーム参加者に送る。
 */
final class ChatMessageReceivedNotification extends BusinessEventNotification
{
    public function __construct(private readonly ChatMessage $chatMessage) {}

    protected function notificationType(): string
    {
        return 'chat_message_received';
    }

    protected function title(): string
    {
        return "{$this->chatMessage->sender->name}さんからメッセージが届きました";
    }

    protected function message(): string
    {
        return Str::limit($this->chatMessage->body, 50);
    }

    protected function url(): string
    {
        return route('chat.show', $this->chatMessage->chat_room_id);
    }
}
