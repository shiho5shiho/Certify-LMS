<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\QaReply\StoreRequest;
use App\Http\Requests\QaReply\UpdateRequest;
use App\Models\QaReply;
use App\Models\QaThread;
use App\UseCases\QaReply\DestroyAction;
use App\UseCases\QaReply\StoreAction;
use App\UseCases\QaReply\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 質問掲示板の回答 Controller。
 */
class QaReplyController extends Controller
{
    public function store(QaThread $thread, StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $reply = $action($request->user(), $thread, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->withFragment('reply-'.$reply->id)
            ->with('success', '回答を投稿しました。');
    }

    public function edit(QaThread $thread, QaReply $reply): View
    {
        $this->ensureBelongsToThread($thread, $reply);
        $this->authorize('update', $reply);

        return view('qa-thread.reply-edit', [
            'thread' => $thread,
            'reply' => $reply,
        ]);
    }

    public function update(QaThread $thread, QaReply $reply, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $this->ensureBelongsToThread($thread, $reply);

        $action($reply, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->withFragment('reply-'.$reply->id)
            ->with('success', '回答を更新しました。');
    }

    public function destroy(Request $request, QaThread $thread, QaReply $reply, DestroyAction $action): RedirectResponse
    {
        $this->ensureBelongsToThread($thread, $reply);
        $this->authorize('delete', $reply);

        $isAdminContext = $request->routeIs('admin.*');
        $action($reply);

        return redirect()
            ->route($isAdminContext ? 'admin.qa-board.show' : 'qa-board.show', $thread)
            ->with('success', '回答を削除しました。');
    }

    /**
     * ネストしたルートパラメータ({thread}/{reply})の整合性を保証する。
     * reply が thread に属さない場合は 404 とする(URL 直叩き対策)。
     */
    private function ensureBelongsToThread(QaThread $thread, QaReply $reply): void
    {
        if ($reply->qa_thread_id !== $thread->id) {
            throw new NotFoundHttpException;
        }
    }
}
