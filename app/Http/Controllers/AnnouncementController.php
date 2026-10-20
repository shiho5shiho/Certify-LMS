<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\Announcement\NoAnnouncementRecipientsException;
use App\Http\Requests\Announcement\StoreRequest;
use App\Models\Announcement;
use App\UseCases\Announcement\CreateAction;
use App\UseCases\Announcement\IndexAction;
use App\UseCases\Announcement\ShowAction;
use App\UseCases\Announcement\StoreAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * admin 用の管理者お知らせ Controller。配信履歴の一覧・詳細と、新規配信を提供する。
 * 配信の不可逆性(要件)により、編集・削除・再配信のアクションは持たない。
 */
class AnnouncementController extends Controller
{
    public function index(IndexAction $action): View
    {
        $this->authorize('viewAny', Announcement::class);

        return view('announcement.management.index', [
            'announcements' => $action(),
        ]);
    }

    public function create(CreateAction $action): View
    {
        $this->authorize('create', Announcement::class);

        return view('announcement.management.create', $action());
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        try {
            $announcement = $action($request->user(), $request->validated());
        } catch (NoAnnouncementRecipientsException $e) {
            // 共通の Handler は入力を引き継がないため、本文を打ち直さずに対象だけ選び直せるよう、ここで入力を残して戻す
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.announcements.show', $announcement)
            ->with('success', 'お知らせを配信しました。');
    }

    public function show(Announcement $announcement, ShowAction $action): View
    {
        $this->authorize('view', $announcement);

        return view('announcement.management.show', [
            'announcement' => $action($announcement),
        ]);
    }
}
