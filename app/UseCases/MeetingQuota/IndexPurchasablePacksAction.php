<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Models\MeetingPack;
use Illuminate\Database\Eloquent\Collection;

/**
 * 受講生が購入できる面談パック(公開中のみ)を並び順で取得するユースケース。
 */
final class IndexPurchasablePacksAction
{
    /**
     * @return Collection<int, MeetingPack>
     */
    public function __invoke(): Collection
    {
        return MeetingPack::query()->published()->ordered()->get();
    }
}
