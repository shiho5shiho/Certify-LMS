<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateRequest;
use App\UseCases\Profile\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 全ロール共通のプロフィール設定画面 (`/settings/profile`)。
 *
 * `edit` がタブ付き設定画面を描画し、`update` が氏名 / 自己紹介(コーチは固定面談 URL も)を更新する。
 * 対象は常にログイン中の本人で、修了済受講生も利用できる。
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.profile', [
            'user' => $request->user(),
        ]);
    }

    public function update(UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($request->user(), $request->validated());

        return redirect()
            ->route('settings.profile.edit')
            ->with('success', 'プロフィールを更新しました。');
    }
}
