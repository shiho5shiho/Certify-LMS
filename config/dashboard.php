<?php

declare(strict_types=1);

/**
 * ダッシュボード関連の設定。
 *
 * 管理者ダッシュボードの集計(全体 KPI / 資格別修了率)は全受講登録を走査する重い集計のため、
 * 結果をキャッシュして連続表示での再計算を避ける。キャッシュは受講状態の遷移時
 * (EnrollmentStatusChangeService::recordStatusChange)に削除され、それ以外の変化は保存時間の経過で反映される。
 */
return [
    'admin_kpi_cache_key' => 'dashboard:admin:kpi',
    'admin_completion_rate_cache_key' => 'dashboard:admin:completion_rate',

    // キャッシュの保存時間(秒)
    'admin_cache_ttl' => (int) env('DASHBOARD_ADMIN_CACHE_TTL', 300),
];
