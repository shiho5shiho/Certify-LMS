<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Models\Announcement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 管理者お知らせの配信履歴を、配信日時の新しい順・1 ページ 20 件で返すユースケース。
 */
final class IndexAction
{
    public function __invoke(int $perPage = 20): LengthAwarePaginator
    {
        return Announcement::query()
            ->with(['targetCertification', 'targetUser', 'createdBy'])
            ->orderByDesc('dispatched_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}
