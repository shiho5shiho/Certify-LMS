<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;

/**
 * 個人目標を未達成に戻す。
 * 既に未達成の場合はエラーにせず、そのまま正常終了する(PM 確認済)。
 */
final class UnmarkAchievedAction
{
    public function __invoke(EnrollmentGoal $goal): EnrollmentGoal
    {
        $goal->update(['achieved_at' => null]);

        return $goal;
    }
}
