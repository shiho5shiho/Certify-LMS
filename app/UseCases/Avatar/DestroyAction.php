<?php

declare(strict_types=1);

namespace App\UseCases\Avatar;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 本人アイコン画像の削除ユースケース。avatar_url を null に戻し(イニシャル表示に戻る)、commit 後に実ファイルを削除する。
 */
final class DestroyAction
{
    public function __invoke(User $user): User
    {
        return DB::transaction(function () use ($user) {
            $path = StoreAction::toStoragePath($user->avatar_url);

            $user->update(['avatar_url' => null]);

            if ($path !== null) {
                DB::afterCommit(fn () => Storage::disk('public')->delete($path));
            }

            return $user->fresh();
        });
    }
}
