<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Models\Announcement;

/**
 * 管理者お知らせ 1 件を、詳細画面で使うリレーション込みで返すユースケース。
 */
final class ShowAction
{
    public function __invoke(Announcement $announcement): Announcement
    {
        return $announcement->loadMissing(['targetCertification', 'targetUser', 'createdBy']);
    }
}
