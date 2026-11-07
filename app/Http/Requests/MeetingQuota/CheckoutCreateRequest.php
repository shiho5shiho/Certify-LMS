<?php

declare(strict_types=1);

namespace App\Http\Requests\MeetingQuota;

use App\Enums\MeetingPackStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutCreateRequest extends FormRequest
{
    /**
     * 受講生かつ学習中であることは、ルートの role:student / active-learning middleware で保証する。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // 公開中のパックのみ購入可(フォームの値を書き換えて非公開パックを指定しても通さない)
            'meeting_pack_id' => [
                'required',
                'ulid',
                Rule::exists('meeting_packs', 'id')->where('status', MeetingPackStatus::Published->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'meeting_pack_id' => '面談パック',
        ];
    }
}
