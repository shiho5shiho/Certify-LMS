<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;

/**
 * 個人目標を達成済にする。
 * 既に達成済の場合はエラーにせず、最初の達成日時を保持する(PM 確認済)。
 */
final class MarkAchievedAction
{
    public function __invoke(EnrollmentGoal $goal): EnrollmentGoal
    {
        if ($goal->isAchieved()) {
            return $goal;
        }

        $goal->update(['achieved_at' => now()]);

        return $goal;
    }
}
