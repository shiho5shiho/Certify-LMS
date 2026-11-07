<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;

/**
 * admin用の面談パック詳細を取得するユースケース。作成者/更新者と、購入履歴(直近 20 件)をEager Loadingで揃える。
 * 購入履歴は購入者も合わせて取得し、新しい順に並べる(画面の「購入履歴(直近 20 件)」に合わせる)。
 */
final class ShowAction
{
    public function __invoke(MeetingPack $meetingPack): MeetingPack
    {
        return $meetingPack->load([
            'createdBy',
            'updatedBy',
            'payments' => fn ($query) => $query->with('user')->latest()->limit(20),
        ]);
    }
}
