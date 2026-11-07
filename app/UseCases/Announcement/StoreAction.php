<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Exceptions\Announcement\NoAnnouncementRecipientsException;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\Announcement\AdminAnnouncementNotification;
use App\Services\AnnouncementRecipientService;
use App\Services\NotificationDispatchService;
use Illuminate\Support\Facades\DB;

/**
 * お知らせを配信するユースケース。
 *
 * 1. 配信対象タイプから候補を集め、通知の共通ルールで受信者を確定する
 * 2. 受信者が 0 人なら配信せず例外にする(履歴も作らない、PM 確認済)
 * 3. 配信履歴の作成と通知の送信予約を 1 トランザクションで行う。
 *    通知はコミット後にキューへ積まれ、worker が送る(T-A-05)。
 *    履歴の作成に失敗した場合は通知も積まれない。送信の失敗はキューのリトライ・失敗ジョブの再投入で対応する
 */
final class StoreAction
{
    public function __construct(
        private readonly AnnouncementRecipientService $recipientService,
        private readonly NotificationDispatchService $notifier,
    ) {}

    /**
     * @param array{title: string, body: string, target_type: string, target_certification_id?: string, target_user_id?: string} $validated
     */
    public function __invoke(User $admin, array $validated): Announcement
    {
        $type = AnnouncementTargetType::from($validated['target_type']);
        $certificationId = $validated['target_certification_id'] ?? null;
        $userId = $validated['target_user_id'] ?? null;

        $recipients = $this->notifier->filterRecipients(
            $this->recipientService->candidates($type, $certificationId, $userId),
            $admin,
        );

        if ($recipients->isEmpty()) {
            throw new NoAnnouncementRecipientsException;
        }

        return DB::transaction(function () use ($admin, $validated, $type, $certificationId, $userId, $recipients) {
            $announcement = Announcement::create([
                'title' => $validated['title'],
                'body' => $validated['body'],
                'target_type' => $type,
                'target_certification_id' => $certificationId,
                'target_user_id' => $userId,
                'dispatched_count' => $recipients->count(),
                'dispatched_at' => now(),
                'created_by_user_id' => $admin->id,
            ]);

            $this->notifier->send($recipients, $admin, new AdminAnnouncementNotification($announcement));

            return $announcement;
        });
    }
}
