<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MeetingPackStatus;
use App\Http\Requests\MeetingPack\IndexRequest;
use App\Http\Requests\MeetingPack\StoreRequest;
use App\Http\Requests\MeetingPack\UpdateRequest;
use App\Models\MeetingPack;
use App\UseCases\MeetingPack\ArchiveAction;
use App\UseCases\MeetingPack\DestroyAction;
use App\UseCases\MeetingPack\IndexAction;
use App\UseCases\MeetingPack\PublishAction;
use App\UseCases\MeetingPack\ShowAction;
use App\UseCases\MeetingPack\StoreAction;
use App\UseCases\MeetingPack\UnarchiveAction;
use App\UseCases\MeetingPack\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * admin用の面談パック管理画面Controller。CRUDと公開状態遷移（publish / archive / unarchive）を提供する。
 */
class MeetingPackController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();

        $plans = $action(
            keyword: $validated['keyword'] ?? null,
            status: isset($validated['status']) ? MeetingPackStatus::from($validated['status']) : null,
        );

        return view('meeting-pack.management.index', [
            'plans' => $plans,
            'keyword' => $validated['keyword'] ?? '',
            'status' => $validated['status'] ?? '',
        ]);
    }

    public function show(MeetingPack $meetingPack, ShowAction $action): View
    {
        $this->authorize('view', $meetingPack);

        return view('meeting-pack.management.show', [
            'plan' => $action($meetingPack),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', MeetingPack::class);

        return view('meeting-pack.management.create');
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $meetingPack = $action($request->user(), $request->validated());

        return redirect()
            ->route('admin.meeting-packs.show', $meetingPack)
            ->with('success', '面談パックを作成しました。');
    }

    public function edit(MeetingPack $meetingPack): View
    {
        $this->authorize('update', $meetingPack);

        return view('meeting-pack.management.edit', [
            'plan' => $meetingPack,
        ]);
    }

    public function update(MeetingPack $meetingPack, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($meetingPack, $request->user(), $request->validated());

        return redirect()
            ->route('admin.meeting-packs.show', $meetingPack)
            ->with('success', '面談パックを更新しました。');
    }

    public function destroy(MeetingPack $meetingPack, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $meetingPack);

        $action($meetingPack);

        return redirect()
            ->route('admin.meeting-packs.index')
            ->with('success', '面談パックを削除しました。');
    }

    public function publish(MeetingPack $meetingPack, PublishAction $action): RedirectResponse
    {
        $this->authorize('publish', $meetingPack);

        $action($meetingPack, request()->user());

        return redirect()
            ->route('admin.meeting-packs.show', $meetingPack)
            ->with('success', '面談パックを公開しました。');
    }

    public function archive(MeetingPack $meetingPack, ArchiveAction $action): RedirectResponse
    {
        $this->authorize('archive', $meetingPack);

        $action($meetingPack, request()->user());

        return redirect()
            ->route('admin.meeting-packs.show', $meetingPack)
            ->with('success', '面談パックをアーカイブしました。');
    }

    public function unarchive(MeetingPack $meetingPack, UnarchiveAction $action): RedirectResponse
    {
        $this->authorize('unarchive', $meetingPack);

        $action($meetingPack, request()->user());

        return redirect()
            ->route('admin.meeting-packs.show', $meetingPack)
            ->with('success', '面談パックを下書きへ戻しました。');
    }
}
