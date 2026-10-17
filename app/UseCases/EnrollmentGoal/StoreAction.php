<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;

/**
 * 個人目標の追加。作成時点では未達成(achieved_at = NULL)。
 */
final class StoreAction
{
    /**
     * @param array{title: string, description?: string|null, target_date?: string|null} $data
     */
    public function __invoke(Enrollment $enrollment, array $data): EnrollmentGoal
    {
        return $enrollment->goals()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'target_date' => $data['target_date'] ?? null,
        ]);
    }
}
