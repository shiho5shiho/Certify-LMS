<?php

declare(strict_types=1);

namespace App\Http\Requests\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * お知らせ配信リクエスト。admin がタイトル・本文・配信対象を入力する。
 *
 * 対象資格 / 対象受講生の入力欄は画面に常時表示されるため、選んだタイプに対応しない欄は
 * exclude_unless で検証・validated() の対象から外す(誤って選ばれていても保存しない)。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Announcement::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'target_type' => ['required', new Enum(AnnouncementTargetType::class)],
            'target_certification_id' => [
                'exclude_unless:target_type,'.AnnouncementTargetType::Certification->value,
                'required',
                'string',
                Rule::exists('certifications', 'id')->where('status', CertificationStatus::Published->value),
            ],
            'target_user_id' => [
                'exclude_unless:target_type,'.AnnouncementTargetType::User->value,
                'required',
                'string',
                Rule::exists('users', 'id')
                    ->where('role', UserRole::Student->value)
                    ->where('status', UserStatus::InProgress->value)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'body' => '本文',
            'target_type' => '配信対象',
            'target_certification_id' => '対象資格',
            'target_user_id' => '対象受講生',
        ];
    }
}
