<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * admin用の面談パック一覧をキーワード＋ステータスでフィルタして取得するユースケース。
 * 並び順は `MeetingPack::scopeOrdered()`（sort_order昇順 → created_at降順）。
 */
final class IndexAction
{
    public function __invoke(
        ?string $keyword,
        ?MeetingPackStatus $status,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = MeetingPack::query()->ordered();

        $query = MeetingPack::query()->ordered()->keyword($keyword);

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
