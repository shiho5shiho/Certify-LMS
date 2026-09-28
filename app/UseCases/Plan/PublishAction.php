<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanInvalidTransitionException;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * プランを公開（draft → published）するユースケース。
 *
 * @throws PlanInvalidTransitionException 下書き以外からの呼出
 */
final class PublishAction
{
    public function __invoke(Plan $plan, User $admin): Plan
    {
        if ($plan->status !== PlanStatus::Draft) {
            throw PlanInvalidTransitionException::forPublish();
        }

        return DB::transaction(function () use ($plan, $admin) {
            $plan->update([
                'status' => PlanStatus::Published->value,
                'updated_by_user_id' => $admin->id,
            ]);

            return $plan->fresh();
        });
    }
}
