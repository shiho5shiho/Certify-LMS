<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\Announcement\AdminAnnouncementNotification;
use App\Services\AnnouncementRecipientService;
use App\Services\NotificationDispatchService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 配信対象 3 種類(全受講生 / 資格指定 / ユーザー指定)のお知らせと、受信者ごとの通知を投入する。
 *
 * - 受信者は本番と同じ Service で確定する(受講中以外・学習中以外は届かない)
 * - 通知の中身は AdminAnnouncementNotification::toArray() で生成し、本番の通知と揃える
 * - メールは送らない(Seeder 実行のたびに Mailpit へ送らないため)
 *
 * EnrollmentSeeder の後に実行する前提。
 */
class AnnouncementSeeder extends Seeder
{
    public function run(AnnouncementRecipientService $recipientService, NotificationDispatchService $notifier): void
    {
        $admin = User::query()->where('email', 'admin@certify-lms.test')->first();
        $student = User::query()->where('email', 'student@certify-lms.test')->first();
        if ($admin === null || $student === null) {
            return;
        }

        $enrollment = Enrollment::query()
            ->where('user_id', $student->id)
            ->learning()
            ->orderBy('created_at')
            ->first();
        if ($enrollment === null) {
            return;
        }

        $seeds = [
            [
                'type' => AnnouncementTargetType::AllStudents,
                'certification_id' => null,
                'user_id' => null,
                'title' => '年末年始の運営休止について',
                'body' => "12/29〜1/3 は運営チームの対応をお休みします。\nこの期間のチャット・質問への回答は 1/4 以降に順次行います。\n学習画面は通常どおりご利用いただけます。",
                'days_ago' => 3,
            ],
            [
                'type' => AnnouncementTargetType::Certification,
                'certification_id' => $enrollment->certification_id,
                'user_id' => null,
                'title' => '教材の一部を更新しました',
                'body' => "試験範囲の改定に合わせて、教材の一部を更新しました。\n更新箇所は各セクションの冒頭に記載しています。\n学習済みのセクションも、念のためご確認ください。",
                'days_ago' => 2,
            ],
            [
                'type' => AnnouncementTargetType::User,
                'certification_id' => null,
                'user_id' => $student->id,
                'title' => '学習の進み具合について',
                'body' => "ここ 1 週間ほど学習記録が止まっているようです。\nお困りのことがあれば、チャットや面談でお気軽にご相談ください。",
                'days_ago' => 1,
            ],
        ];

        foreach ($seeds as $seed) {
            $dispatchedAt = now()->subDays($seed['days_ago']);
            $recipients = $notifier->filterRecipients(
                $recipientService->candidates($seed['type'], $seed['certification_id'], $seed['user_id']),
                $admin,
            );

            $announcement = Announcement::create([
                'title' => $seed['title'],
                'body' => $seed['body'],
                'target_type' => $seed['type'],
                'target_certification_id' => $seed['certification_id'],
                'target_user_id' => $seed['user_id'],
                'dispatched_count' => $recipients->count(),
                'dispatched_at' => $dispatchedAt,
                'created_by_user_id' => $admin->id,
            ]);

            foreach ($recipients as $recipient) {
                $notification = new AdminAnnouncementNotification($announcement);
                $notification->id = (string) Str::uuid();

                $recipient->notifications()->create([
                    'id' => $notification->id,
                    'type' => $notification::class,
                    'data' => $notification->toArray($recipient),
                    'read_at' => null,
                    'created_at' => $dispatchedAt,
                    'updated_at' => $dispatchedAt,
                ]);
            }
        }
    }
}
