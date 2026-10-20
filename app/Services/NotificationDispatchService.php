<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\BusinessEventNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * 業務イベント通知を、受信対象に絞り込んでから送る Service。
 *
 * - 操作した本人は除く(本人は操作直後の画面の完了メッセージで結果が分かるため)
 * - 管理者は除く(チケットのスコープ外「管理者向けの通知」)
 * - 受講生は受講中(in_progress)のみ
 * - コーチはロールのみで通す。有効な担当コーチかどうかは、呼出側が担当割当から候補を渡すことで担保する
 * - 退会済ユーザーは論理削除によりリレーションから null になるため、null は除く
 */
final class NotificationDispatchService
{
    /**
     * @param iterable<int, User|null> $candidates
     */
    public function send(iterable $candidates, User $actor, BusinessEventNotification $notification): void
    {
        $recipients = $this->filterRecipients($candidates, $actor);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, $notification);
    }

    /**
     * 受信対象の共通ルール(クラスコメント参照)で候補を絞り込み、実際に送る相手を返す。
     * 送る前に人数を知りたい呼出側(管理者お知らせの 0 人チェック・配信件数)からも使う。
     *
     * @param iterable<int, User|null> $candidates
     *
     * @return Collection<int, User>
     */
    public function filterRecipients(iterable $candidates, User $actor): Collection
    {
        return collect($candidates)
            ->filter()
            ->unique('id')
            ->reject(fn (User $user) => $user->id === $actor->id)
            ->filter(fn (User $user) => match ($user->role) {
                UserRole::Student => $user->status === UserStatus::InProgress,
                UserRole::Coach => true,
                UserRole::Admin => false,
            })
            ->values();
    }
}
