<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AnnouncementTargetType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * 管理者お知らせの配信対象タイプから、受信者の候補を集める Service。
 *
 * - 全受講生: 受講生ロールの全ユーザー
 * - 資格指定: その資格を「学習中」の受講登録を持つ受講生(修了・学習中止・受講解除は除く、PM 確認済)
 * - ユーザー指定: 指定した 1 名
 *
 * 受講中(in_progress)以外の除外は NotificationDispatchService::filterRecipients() の共通ルールに任せる。
 */
final class AnnouncementRecipientService
{
    /**
     * @return Collection<int, User>
     */
    public function candidates(AnnouncementTargetType $type, ?string $certificationId, ?string $userId): Collection
    {
        return match ($type) {
            AnnouncementTargetType::AllStudents => User::query()
                ->where('role', UserRole::Student)
                ->get(),
            AnnouncementTargetType::Certification => User::query()
                ->where('role', UserRole::Student)
                ->whereHas('enrollments', fn (Builder $query) => $query
                    ->where('certification_id', $certificationId)
                    ->learning())
                ->get(),
            AnnouncementTargetType::User => User::query()
                ->whereKey($userId)
                ->get(),
        };
    }
}
