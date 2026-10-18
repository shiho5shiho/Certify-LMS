<?php

declare(strict_types=1);

namespace App\UseCases\Profile;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 本人プロフィール(氏名 / 自己紹介 / コーチのみ固定面談 URL)の更新ユースケース。
 */
final class UpdateAction
{
    /**
     * @param array{name: string, bio?: ?string, meeting_url?: string} $validated Profile/UpdateRequest::rules() で検証済
     */
    public function __invoke(User $user, array $validated): User
    {
        return DB::transaction(function () use ($user, $validated) {
            $user->update($validated);

            return $user->fresh();
        });
    }
}
