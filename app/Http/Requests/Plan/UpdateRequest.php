<?php

declare(strict_types=1);

namespace App\Http\Requests\Plan;

use Illuminate\Foundation\Http\FormRequest;

/**
 * プラン更新リクエスト。status は本リクエストでは更新しない（公開状態遷移用エンドポイント: publish / archive / unarchive から別途行う）。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('plan')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_days' => ['required', 'integer', 'between:1,3650'],
            'default_meeting_quota' => ['required', 'integer', 'between:0,1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'プラン名',
            'description' => '説明',
            'duration_days' => '受講期間',
            'default_meeting_quota' => '初期付与面談回数',
            'sort_order' => '並び順',
        ];
    }
}
