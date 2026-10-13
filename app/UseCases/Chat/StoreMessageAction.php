<?php

declare(strict_types=1);

namespace App\UseCases\Chat;

use App\Enums\UserRole;
use App\Events\ChatMessageSent;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use App\Notifications\Chat\ChatMessageReceivedNotification;
use App\Services\NotificationDispatchService;
use Illuminate\Support\Facades\DB;

/**
 * ChatRoom にメッセージを INSERT し、送信者の既読時刻を更新したうえで Broadcast を発火する Action。
 *
 * - INSERT 後、ChatMessage::booted() が `chat_rooms.last_message_at` を denormalize 更新する
 * - 送信者自身の `ChatMember.last_read_at = now()` を UPDATE(自分のメッセージは未読としてカウントしない)
 * - 通信失敗が DB 整合性に波及しないよう Pusher Broadcast は `DB::afterCommit()` で送る
 * - 担当コーチ未割当の判定は Controller 側で実施済(`CertificationCoachNotAssignedForChatException` 振り分け)
 */
final class StoreMessageAction
{
    public function __construct(
        private readonly NotificationDispatchService $notifier,
    ) {}

    /**
     * @param array{body: string} $validated
     */
    public function __invoke(User $sender, ChatRoom $room, array $validated): ChatMessage
    {
        return DB::transaction(function () use ($sender, $room, $validated) {
            $message = ChatMessage::create([
                'chat_room_id' => $room->id,
                'sender_user_id' => $sender->id,
                'body' => $validated['body'],
            ]);

            ChatMember::query()
                ->where('chat_room_id', $room->id)
                ->where('user_id', $sender->id)
                ->update(['last_read_at' => now()]);

            DB::afterCommit(function () use ($message, $room, $sender): void {
                broadcast(new ChatMessageSent($message->load('sender')))->toOthers();

                // コーチは現在の担当コーチのみに送る(担当解除後も ChatMember には残るため、担当割当で絞る)
                $room->loadMissing(['members.user', 'enrollment.certification.coaches']);
                $assignedCoachIds = $room->enrollment->certification->coaches->pluck('id');
                $candidates = $room->members
                    ->pluck('user')
                    ->filter(fn (?User $user) => $user !== null
                        && ($user->role !== UserRole::Coach || $assignedCoachIds->contains($user->id)));

                $this->notifier->send(
                    $candidates,
                    $sender,
                    new ChatMessageReceivedNotification($message),
                );
            });

            return $message;
        });
    }
}
