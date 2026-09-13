<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaThread;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 質問掲示板の一覧取得。ロールに応じた可視範囲(QaThread::scopeVisibleTo)を必ず適用し、
 * status / certification_id / keyword でさらに絞り込む。新着順・paginate(20)。
 *
 * with/withCount であらかじめ関連を読み込み、一覧表示時の N+1 を防ぐ。
 */
final class IndexAction
{
    /**
     * @param array{status?: string|null, certification_id?: string|null, keyword?: string|null} $filters
     */
    public function __invoke(User $viewer, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return QaThread::query()
            ->visibleTo($viewer)
            ->with(['user', 'certification'])
            ->withCount('replies')
            ->status($filters['status'] ?? null)
            ->certification($filters['certification_id'] ?? null)
            ->keyword($filters['keyword'] ?? null)
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
