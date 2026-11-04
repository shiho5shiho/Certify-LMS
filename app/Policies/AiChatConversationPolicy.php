<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AiChatConversation;
use App\Models\User;

/**
 * AI 相談の会話の認可ルール。
 *
 * - 利用できるのは受講生のみ(学習中かどうかはルートの active-learning Middleware で判定する)
 * - 会話の閲覧・タイトル編集・削除・メッセージ送信は、会話のオーナー本人のみ
 * - 管理者・コーチは他の受講生の会話を閲覧できない(要件: プライバシー / 監査外)
 */
class AiChatConversationPolicy
{
    public function create(User $user): bool
    {
        return $user->role === UserRole::Student;
    }

    public function view(User $user, AiChatConversation $conversation): bool
    {
        return $this->isOwner($user, $conversation);
    }

    public function update(User $user, AiChatConversation $conversation): bool
    {
        return $this->isOwner($user, $conversation);
    }

    public function delete(User $user, AiChatConversation $conversation): bool
    {
        return $this->isOwner($user, $conversation);
    }

    public function sendMessage(User $user, AiChatConversation $conversation): bool
    {
        return $this->isOwner($user, $conversation);
    }

    private function isOwner(User $user, AiChatConversation $conversation): bool
    {
        return $user->role === UserRole::Student && $conversation->user_id === $user->id;
    }
}
