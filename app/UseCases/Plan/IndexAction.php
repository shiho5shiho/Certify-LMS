<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * admin用のプラン一覧をキーワード＋ステータスでフィルタして取得するユースケース。
 * 並び順は `Plan::scopeOrdered()`（sort_order昇順 → created_at降順）。
 */
final class IndexAction
{
    public function __invoke(
        ?string $keyword,
        ?PlanStatus $status,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = Plan::query()->ordered()->keyword($keyword)->withCount('users');

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
