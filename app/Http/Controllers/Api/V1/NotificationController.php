<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\IndexRequest;
use App\Http\Resources\NotificationResource;
use App\UseCases\Notification\IndexAction;
use App\UseCases\Notification\MarkAllAsReadAction;
use App\UseCases\Notification\MarkAsReadAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Notifications\DatabaseNotification;

/**
 * 通知ポップオーバー用の JSON API Controller。Sanctum Cookie 認証で保護し、自分宛の通知のみを扱う。
 * 一覧・既読化の処理と本人チェックは、Web 側の NotificationController と同じ Action / Policy を使う。
 */
class NotificationController extends Controller
{
    /**
     * 自分宛の通知の最新 20 件と未読件数を返す。tab=unread のときは未読のみ。
     */
    public function index(IndexRequest $request, IndexAction $action): AnonymousResourceCollection
    {
        $user = $request->user();
        $notifications = $action($user, $request->validated('tab') ?? 'all');

        return NotificationResource::collection($notifications->items())
            ->additional(['meta' => ['unread_count' => $user->unreadNotifications()->count()]]);
    }

    /**
     * 通知 1 件を既読にし、遷移先 URL と既読化後の未読件数を返す。他人宛の通知は 403。
     */
    public function markAsRead(Request $request, DatabaseNotification $notification, MarkAsReadAction $action): JsonResponse
    {
        $this->authorize('markAsRead', $notification);

        $action($notification);

        return response()->json([
            'data' => ['url' => $notification->data['url'] ?? route('notifications.index')],
            'meta' => ['unread_count' => $request->user()->unreadNotifications()->count()],
        ]);
    }

    /**
     * 自分宛の未読通知をまとめて既読にし、既読化後の未読件数を返す。
     */
    public function markAllAsRead(Request $request, MarkAllAsReadAction $action): JsonResponse
    {
        $user = $request->user();

        $action($user);

        return response()->json([
            'meta' => ['unread_count' => $user->unreadNotifications()->count()],
        ]);
    }
}
