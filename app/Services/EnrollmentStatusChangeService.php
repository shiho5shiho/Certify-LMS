<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\EnrollmentStatusLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Enrollment 状態遷移の監査ログ(`EnrollmentStatusLog`)を INSERT する Service。
 *
 * 呼出側 Action がトランザクション内で recordStatusChange() を呼ぶ前提。本 Service 自体は
 * DB::transaction() を持たない(`backend-services.md` の規約準拠、ステートレス、INSERT とキャッシュ削除のみ)。
 *
 * `final` 不採用: Mockery で recordStatusChange を mock してトランザクション原子性の rollback 検証を
 * Action テストで行う可能性があるため(`UserStatusChangeService` と同じ判断軸)。
 *
 * 受講状態の遷移はすべて本 Service を通るため、管理者ダッシュボード集計キャッシュの削除もここで行う
 * (新規受講登録 / 修了 / 学習中止(手動・試験日超過の自動)/ 再開)。
 */
final class EnrollmentStatusChangeService
{
    /**
     * @param Enrollment $enrollment 状態遷移する対象 Enrollment
     * @param ?EnrollmentStatus $fromStatus 遷移前ステータス(初回登録時のみ null、それ以降は必須)
     * @param EnrollmentStatus $toStatus 遷移後ステータス
     * @param ?User $changedBy 操作者(null はシステム自動 = Schedule Command 等)
     * @param ?string $reason 変更理由(任意、UI 表示用)
     */
    public function recordStatusChange(
        Enrollment $enrollment,
        ?EnrollmentStatus $fromStatus,
        EnrollmentStatus $toStatus,
        ?User $changedBy,
        ?string $reason = null,
    ): EnrollmentStatusLog {
        $log = $enrollment->statusLogs()->create([
            'from_status' => $fromStatus?->value,
            'to_status' => $toStatus->value,
            'changed_by_user_id' => $changedBy?->id,
            'changed_reason' => $reason,
            'changed_at' => now(),
        ]);

        // 受講状態が変わると管理者ダッシュボードの集計値も変わるため、両方のキャッシュを削除する。
        // 呼出側のトランザクション確定後に削除する(確定前に削除すると、その間に別のリクエストが
        // 変更前の値で再キャッシュしてしまうため)。トランザクション外で呼ばれた場合は即時に実行される。
        DB::afterCommit(function (): void {
            Cache::forget(config('dashboard.admin_kpi_cache_key'));
            Cache::forget(config('dashboard.admin_completion_rate_cache_key'));
        });

        return $log;
    }
}
