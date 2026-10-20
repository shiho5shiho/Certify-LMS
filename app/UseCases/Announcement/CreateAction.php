<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * 配信フォームの選択肢(対象資格 / 対象受講生)を返すユースケース。
 * 受講生は通知が届く受講中(in_progress)のみ、資格は公開中のみを並べる。
 */
final class CreateAction
{
    /**
     * @return array{certifications: Collection<int, Certification>, students: Collection<int, User>}
     */
    public function __invoke(): array
    {
        return [
            'certifications' => Certification::query()->published()->orderBy('name')->get(),
            'students' => User::query()
                ->where('role', UserRole::Student)
                ->where('status', UserStatus::InProgress)
                ->orderBy('name')
                ->get(),
        ];
    }
}
