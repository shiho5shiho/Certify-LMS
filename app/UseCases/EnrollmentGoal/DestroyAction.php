<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;

/**
 * 個人目標の削除。物理削除で、履歴は残さない。
 */
final class DestroyAction
{
    public function __invoke(EnrollmentGoal $goal): void
    {
        $goal->delete();
    }
}
