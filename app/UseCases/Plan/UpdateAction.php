<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * プランを更新するユースケース。`status` は本Actionでは更新せず、公開状態遷移用Actionに責務分離する。
 *
 * TODO(Q2 PM回答待ち): 現状は受講者が紐づく公開中プランでも無条件に編集可能。
 * 「延長時に最新値が使われてよいか／契約時点の値を維持すべきか」の回答が来たら、
 * 必要であれば受講者紐づけ有無によるガードをここに追加する。
 */
final class UpdateAction
{
    /**
     * @param array{name: string, description?: ?string, duration_days: int, default_meeting_quota: int, price: int, stripe_price_id?: ?string, sort_order?: ?int} $validated
     */
    public function __invoke(Plan $plan, User $admin, array $validated): Plan
    {
        return DB::transaction(function () use ($plan, $admin, $validated) {
            $plan->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'duration_days' => $validated['duration_days'],
                'default_meeting_quota' => $validated['default_meeting_quota'],
                'sort_order' => $validated['sort_order'] ?? $plan->sort_order,
                'updated_by_user_id' => $admin->id,
            ]);

            return $plan->fresh();
        });
    }
}
