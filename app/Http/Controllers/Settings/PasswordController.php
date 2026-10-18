<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

/**
 * 本人パスワード変更 (`PUT /settings/password`)。
 *
 * 検証と更新は Fortify の UpdateUserPassword Action に委譲する(現在のパスワード確認 + 新パスワード確認入力)。
 * エラーは updatePassword バッグに入り、パスワードタブにそのまま表示される。
 */
class PasswordController extends Controller
{
    public function update(Request $request, UpdatesUserPasswords $updater): RedirectResponse
    {
        $updater->update($request->user(), $request->all());

        return redirect()
            ->route('settings.profile.edit', ['tab' => 'password'])
            ->with('success', 'パスワードを変更しました。');
    }
}
