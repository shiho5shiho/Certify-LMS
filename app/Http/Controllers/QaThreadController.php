<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Http\Requests\QaThread\StoreRequest;
use App\Http\Requests\QaThread\UpdateRequest;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use App\UseCases\QaThread\DestroyAction;
use App\UseCases\QaThread\IndexAction;
use App\UseCases\QaThread\ResolveAction;
use App\UseCases\QaThread\StoreAction;
use App\UseCases\QaThread\UnresolveAction;
use App\UseCases\QaThread\UpdateAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 質問掲示板(質問スレッド)Controller。
 *
 * 受講生・コーチ向け(`qa-board.*`)と管理者モデレーション(`admin.qa-board.*`)の両ルートから呼ばれる。
 * どちらのコンテキストかは `request()->routeIs('admin.*')` で分岐し、可視範囲・操作可否は Policy に委譲する。
 */
class QaThreadController extends Controller
{
    public function index(Request $request, IndexAction $action): View
    {
        $viewer = $request->user();
        $isAdminContext = $request->routeIs('admin.*');

        $filters = [
            'status' => $request->string('status')->toString() ?: null,
            'certification_id' => $request->string('certification_id')->toString() ?: null,
            'keyword' => $request->string('keyword')->toString() ?: null,
        ];

        $threads = $action($viewer, $filters);

        return view('qa-thread.index', [
            'threads' => $threads,
            'filters' => $filters,
            'certifications' => $this->filterableCertifications($viewer, $isAdminContext),
            'publishedStatus' => CertificationStatus::Published,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', QaThread::class);

        return view('qa-thread.create', [
            'certifications' => Certification::query()
                ->published()
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $thread = $action($request->user(), $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を投稿しました。');
    }

    public function show(Request $request, QaThread $thread): View
    {
        $this->authorize('view', $thread);

        $thread->load(['certification', 'user', 'replies.user']);

        return view('qa-thread.show', [
            'thread' => $thread,
        ]);
    }

    public function edit(QaThread $thread): View
    {
        $this->authorize('update', $thread);

        return view('qa-thread.edit', [
            'thread' => $thread,
        ]);
    }

    public function update(QaThread $thread, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($thread, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を更新しました。');
    }

    public function destroy(Request $request, QaThread $thread, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $thread);

        $isAdminContext = $request->routeIs('admin.*');
        $action($thread);

        return redirect()
            ->route($isAdminContext ? 'admin.qa-board.index' : 'qa-board.index')
            ->with('success', '質問を削除しました。');
    }

    public function resolve(QaThread $thread, ResolveAction $action): RedirectResponse
    {
        $this->authorize('resolve', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '解決済みにしました。');
    }

    public function unresolve(QaThread $thread, UnresolveAction $action): RedirectResponse
    {
        $this->authorize('unresolve', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '未解決に戻しました。');
    }

    /**
     * 一覧の絞り込みチップに出す資格一覧。admin は全資格(公開停止含む)、
     * coach は担当かつ公開中、student は公開中すべて。
     *
     * @return Collection<int, Certification>
     */
    private function filterableCertifications(User $viewer, bool $isAdminContext): Collection
    {
        if ($isAdminContext) {
            return Certification::query()->orderBy('name')->get();
        }

        return match ($viewer->role) {
            UserRole::Coach => Certification::query()
                ->published()
                ->assignedTo($viewer)
                ->orderBy('name')
                ->get(),
            default => Certification::query()->published()->orderBy('name')->get(),
        };
    }
}
