<?php

declare(strict_types=1);

namespace App\Http\Requests\EnrollmentGoal;

use App\Models\EnrollmentGoal;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 個人目標の編集リクエスト。受講生本人のみ通過する。
 * 目標期日は任意で、過去の日付も許容する(PM 確認済)。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $goal = $this->route('goal');

        return $goal instanceof EnrollmentGoal
            && ($this->user()?->can('update', $goal) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'target_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => '目標',
            'description' => '詳細',
            'target_date' => '目標期日',
        ];
    }
}
