<?php

declare(strict_types=1);

namespace App\Http\Requests\MeetingPack;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 面談パック更新リクエスト。admin が基本情報のみ更新できる。
 * status は本リクエストでは更新しない（公開状態遷移用エンドポイント: publish / archive / unarchive から別途行う）。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('meeting_pack')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'meeting_count' => ['required', 'integer', 'between:1,100'],
            'price' => ['required', 'integer', 'between:0,1000000'],
            'stripe_price_id' => ['nullable', 'string', 'max:255'],
            // TODO: 上限値はPM確認待ち（現状は下限0のみ、上限なし）
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'SKU名',
            'description' => '説明',
            'meeting_count' => '面談回数',
            'price' => '価格',
            'stripe_price_id' => 'Stripe Price ID',
            'sort_order' => '並び順',
        ];
    }
}
