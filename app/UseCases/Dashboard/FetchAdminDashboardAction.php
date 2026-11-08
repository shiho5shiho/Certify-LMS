<?php

declare(strict_types=1);

namespace App\UseCases\Dashboard;

use App\Http\Controllers\DashboardController;
use App\Models\User;
use App\Services\EnrollmentStatsService;
use App\UseCases\Dashboard\ViewModels\AdminDashboardViewModel;
use Illuminate\Support\Facades\Cache;

/**
 * 管理者ダッシュボードの ViewModel を組み立てる Action。
 *
 * 全体 KPI(learning / passed / failed)/ 資格別受講中人数 上位 10 / 資格別修了率 を集約する。
 * 修了申請待ち一覧 / プラン期限切れ / 滞留検知 / 直近通知は本ロールでは表示しない
 * (admin 宛通知は notification spec で発火しないため、admin 通知導線は実用上死に機能になる)。
 *
 * 本 Action は集計の取得とセクション単位の例外フォールバック(safe)のみを担う(薄い集約に保つ)。
 * 集計結果は config('dashboard.*') のキーで保存時間の間キャッシュし、受講状態の遷移時に
 * EnrollmentStatusChangeService で削除される。集計で例外が出た場合は Cache::remember が保存しないため、
 * 次回の表示で再集計される。
 *
 * @see DashboardController::index()
 */
final class FetchAdminDashboardAction
{
    use HasDashboardSafeFetch;

    public function __construct(
        private readonly EnrollmentStatsService $stats,
    ) {}

    public function __invoke(User $admin): AdminDashboardViewModel
    {
        $ttl = (int) config('dashboard.admin_cache_ttl');

        $kpi = $this->safe(fn () => Cache::remember(
            config('dashboard.admin_kpi_cache_key'),
            $ttl,
            fn () => $this->stats->adminKpi(),
        ));

        $completionRate = $this->safe(fn () => Cache::remember(
            config('dashboard.admin_completion_rate_cache_key'),
            $ttl,
            fn () => $this->stats->completionRateByCertification(),
        ));

        $byCertificationTop10 = $kpi !== null
            ? collect($kpi['by_certification'])->take(10)
            : collect();

        $isEmptyState = $kpi === null
            ? true
            : ($kpi['learning_count'] + $kpi['passed_count'] + $kpi['failed_count'] === 0);

        return new AdminDashboardViewModel(
            kpi: $kpi,
            byCertificationTop10: $byCertificationTop10,
            completionRateByCertification: $completionRate,
            isEmptyState: $isEmptyState,
        );
    }
}
