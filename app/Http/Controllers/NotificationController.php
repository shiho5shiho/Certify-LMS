<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Notification\IndexRequest;
use App\UseCases\Notification\IndexAction;
use App\UseCases\Notification\MarkAllAsReadAction;
use App\UseCases\Notification\MarkAsReadAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * 通知(アプリ内通知)の一覧・既読化 Controller。全ロール共通で、自分宛の通知のみを扱う。
 */
class NotificationController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View|RedirectResponse
    {
        $user = $request->user();
        $tab = $request->validated('tab') ?? 'all';
        $notifications = $action($user, $tab);

        // タブ切替でページ番号が引き継がれ範囲外になった場合は、同じタブの 1 ページ目に戻す
        if ($notifications->isEmpty() && $notifications->currentPage() > 1) {
            return redirect()->route('notifications.index', ['tab' => $tab]);
        }

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications()->count(),
            'tab' => $tab,
        ]);
    }

    /**
     * 通知詳細(運営お知らせなど、遷移先の業務画面を持たない通知の全文表示)。
     * メールのボタンから直接開いた場合も読んだことになるよう、表示時に既読にする。
     */
    public function show(DatabaseNotification $notification, MarkAsReadAction $action): View
    {
        $this->authorize('view', $notification);

        $action($notification);

        return view('notifications.show', [
            'notification' => $notification,
        ]);
    }

    public function markAsRead(DatabaseNotification $notification, MarkAsReadAction $action): RedirectResponse
    {
        $this->authorize('markAsRead', $notification);

        $action($notification);

        return redirect()->to($notification->data['url'] ?? route('notifications.index'));
    }

    public function markAllAsRead(Request $request, MarkAllAsReadAction $action): RedirectResponse
    {
        $action($request->user());

        return redirect()
            ->route('notifications.index')
            ->with('success', 'すべての通知を既読にしました。');
    }
}
