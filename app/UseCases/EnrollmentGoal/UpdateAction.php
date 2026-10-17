<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;

/**
 * 個人目標の編集。タイトル / 詳細 / 目標期日を更新し、達成状態は変更しない。
 */
final class UpdateAction
{
    /**
     * @param array{title: string, description?: string|null, target_date?: string|null} $data
     */
    public function __invoke(EnrollmentGoal $goal, array $data): EnrollmentGoal
    {
        $goal->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'target_date' => $data['target_date'] ?? null,
        ]);

        return $goal;
    }
}
