<?php

declare(strict_types=1);

namespace App\UseCases\Avatar;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 本人アイコン画像のアップロード(差し替え)ユースケース。
 *
 * `avatars/{ulid}.{ext}` で public disk に保存し、`/storage/...` 形式の URL を users.avatar_url に保存する。
 * DB 更新に失敗したら新ファイルを削除し、成功したら commit 後に古い画像ファイルを削除する。
 */
final class StoreAction
{
    public function __invoke(User $user, UploadedFile $file): User
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $path = Storage::disk('public')->putFileAs('avatars', $file, Str::ulid().".{$ext}");
        $oldPath = self::toStoragePath($user->avatar_url);

        try {
            return DB::transaction(function () use ($user, $path, $oldPath) {
                $user->update(['avatar_url' => '/storage/'.$path]);

                if ($oldPath !== null) {
                    DB::afterCommit(fn () => Storage::disk('public')->delete($oldPath));
                }

                return $user->fresh();
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }
    }

    /**
     * `/storage/avatars/xxx.png` → `avatars/xxx.png`。本機能で保存した画像以外は null(削除しない)。
     */
    public static function toStoragePath(?string $url): ?string
    {
        if ($url === null || ! str_starts_with($url, '/storage/avatars/')) {
            return null;
        }

        return Str::after($url, '/storage/');
    }
}
