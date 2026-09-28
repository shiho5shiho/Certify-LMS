<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Models\Plan;

/**
 * admin用のプラン詳細を取得するユースケース。作成者/更新者/受講者をEager Loadingで揃える。
 */
final class ShowAction
{
    public function __invoke(Plan $plan): Plan
    {
        return $plan->load(['createdBy', 'updatedBy', 'users']);
    }
}
