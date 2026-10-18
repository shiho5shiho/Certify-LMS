<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 本人プロフィール更新リクエスト。氏名 / 自己紹介を受け取り、コーチのみ固定面談 URL も受け取る。
 * メールアドレスは rules に含めないため、送信されても validated() に入らず更新されない。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 更新対象は常にログイン中の本人(ルートに ID を持たない)
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:1000'],
        ];

        if ($this->user()?->role === UserRole::Coach) {
            // 招待時(OnboardingRequest)と同じ条件。空欄での保存は不可(PM 確認済)
            $rules['meeting_url'] = ['required', 'string', 'url', 'max:500'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '氏名',
            'bio' => '自己紹介',
            'meeting_url' => '固定面談 URL',
        ];
    }
}
