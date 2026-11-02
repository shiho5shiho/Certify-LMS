<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Exceptions\Mentoring\GoogleCalendarRequestFailedException;
use App\Http\Controllers\Controller;
use App\Services\Contracts\GoogleCalendarServiceContract;
use App\UseCases\GoogleCalendar\ConnectAction;
use App\UseCases\GoogleCalendar\DisconnectAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * コーチの Google カレンダー連携(OAuth の開始 / コールバック / 解除)。
 *
 * 入口は面談設定タブ。`role:coach` middleware で他ロールは 403。
 * なりすまし防止のため、連携開始時に発行した state をセッションに保存し、コールバックで照合する。
 */
class GoogleCalendarController extends Controller
{
    private const SESSION_STATE = 'google_calendar.state';

    private const SESSION_REDIRECT_PATH = 'google_calendar.redirect_path';

    private const FAILED_MESSAGE = 'Googleカレンダーとの連携に失敗しました。もう一度お試しください。';

    /**
     * state を発行してセッションに保存し、Google の認可画面へリダイレクトする。
     */
    public function redirect(Request $request, GoogleCalendarServiceContract $googleCalendar): RedirectResponse
    {
        $state = Str::random(40);

        $request->session()->put(self::SESSION_STATE, $state);
        $request->session()->put(self::SESSION_REDIRECT_PATH, $this->safeRedirectPath($request->query('redirect_path')));

        return redirect()->away($googleCalendar->authorizationUrl($state));
    }

    /**
     * state を照合し、認可コードをトークンに交換して連携情報を保存する。
     */
    public function callback(Request $request, ConnectAction $action): RedirectResponse
    {
        // pull: 取り出すと同時にセッションから消す(同じ state の使い回しを防ぐ)
        $expectedState = $request->session()->pull(self::SESSION_STATE);
        $redirectPath = $request->session()->pull(self::SESSION_REDIRECT_PATH, $this->safeRedirectPath(null));

        $state = $request->query('state');
        $code = $request->query('code');

        // state 不一致(なりすまし)/ コーチが同意画面で拒否(code なし)のときは保存しない
        if (! is_string($expectedState) || ! is_string($state) || ! hash_equals($expectedState, $state)
            || ! is_string($code) || $code === '') {
            return redirect()->to($redirectPath)->with('error', self::FAILED_MESSAGE);
        }

        try {
            $action($request->user(), $code);
        } catch (GoogleCalendarRequestFailedException $e) {
            Log::warning('Google カレンダーの連携に失敗しました。', [
                'user_id' => $request->user()->id,
                'exception' => $e,
            ]);

            return redirect()->to($redirectPath)->with('error', self::FAILED_MESSAGE);
        }

        return redirect()->to($redirectPath)->with('success', 'Googleカレンダーと連携しました。');
    }

    /**
     * 連携を解除する(Google 側の予定は削除しない)。
     */
    public function destroy(Request $request, DisconnectAction $action): RedirectResponse
    {
        $action($request->user());

        return redirect()
            ->route('settings.availability.index')
            ->with('success', 'Googleカレンダーとの連携を解除しました。');
    }

    /**
     * 連携後の戻り先を LMS 内のパスに限定する(外部サイトへ飛ばされるのを防ぐ)。
     */
    private function safeRedirectPath(mixed $path): string
    {
        if (is_string($path) && str_starts_with($path, '/') && ! str_starts_with($path, '//') && ! str_contains($path, '\\')) {
            return $path;
        }

        return route('settings.availability.index', absolute: false);
    }
}
