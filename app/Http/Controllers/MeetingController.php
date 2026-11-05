<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Http\Requests\Meeting\AvailabilityRequest;
use App\Http\Requests\Meeting\IndexAsCoachRequest;
use App\Http\Requests\Meeting\IndexRequest;
use App\Http\Requests\Meeting\StoreRequest;
use App\Http\Requests\Meeting\UpsertMemoRequest;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Services\MeetingQuotaService;
use App\UseCases\Meeting\CancelAction;
use App\UseCases\Meeting\FetchAvailabilityAction;
use App\UseCases\Meeting\IndexAction;
use App\UseCases\Meeting\IndexAsCoachAction;
use App\UseCases\Meeting\ShowAction;
use App\UseCases\Meeting\StoreAction;
use App\UseCases\Meeting\UpsertMemoAction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 1on1 面談予約 (Meeting) の HTTP エントリポイント。
 *
 * 受講生視点(index / show / create / store / cancel / fetchAvailability)とコーチ視点
 * (indexAsCoach / upsertMemo)を 1 Controller に集約する。業務ロジック・データ取得は
 * app/UseCases/Meeting/ の Action に委譲し、本 Controller はリクエスト受付・認可・レスポンス整形のみを行う。
 * 認可は $this->authorize() または FormRequest::authorize()。
 */
class MeetingController extends Controller
{
    /**
     * 受講生本人の面談一覧。filter (upcoming/past/all) クエリで履歴を切り替える。
     */
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $filter = $request->validated('filter') ?? 'upcoming';

        $result = $action($request->user(), $filter);

        return view('meeting.index', [
            'meetings' => $result['meetings'],
            'filter' => $filter,
            'meetingsRemaining' => $result['meetingsRemaining'],
        ]);
    }

    /**
     * コーチ宛の面談一覧。担当受講生 / 受講登録での絞り込みを併せて提供する。
     */
    public function indexAsCoach(IndexAsCoachRequest $request, IndexAsCoachAction $action): View
    {
        $filters = $request->validated();
        $filter = $filters['filter'] ?? 'upcoming';
        $studentId = $filters['student'] ?? null;
        $enrollmentId = $filters['enrollment'] ?? null;

        return view('meeting.coach.index', [
            'meetings' => $action($request->user(), $filter, $studentId, $enrollmentId),
            'filter' => $filter,
            'studentFilter' => $studentId,
            'enrollmentFilter' => $enrollmentId,
        ]);
    }

    /**
     * 面談詳細(当事者共通)。Policy で coach/student の閲覧範囲を絞る。
     */
    public function show(Meeting $meeting, ShowAction $action): View
    {
        $this->authorize('view', $meeting);

        return view('meeting.show', [
            'meeting' => $action($meeting),
        ]);
    }

    /**
     * 予約画面(受講生): URL に Enrollment を含む正規ルートで表示する。
     */
    public function create(Enrollment $enrollment, MeetingQuotaService $meetingQuota): View
    {
        $this->authorize('create', Meeting::class);

        abort_unless($enrollment->user_id === auth()->id(), 403);
        abort_unless($enrollment->status === EnrollmentStatus::Learning, 403);

        $enrollment->loadMissing('certification');

        return view('meeting.create', [
            'enrollment' => $enrollment,
            'meetingsRemaining' => $meetingQuota->remaining(auth()->user()),
        ]);
    }

    /**
     * 予約画面のエントリポイント(URL に Enrollment 無し)。
     * `resolve-default-enrollment` Middleware が default 資格に redirect するため、
     * 本 method に到達するのは default 未設定 + 残存 Enrollment が 0 件 or 2+ 件のケース。
     */
    public function createFallback(): View
    {
        $user = auth()->user();
        $enrollments = $user
            ?->enrollments()
            ->whereIn('status', [EnrollmentStatus::Learning->value, EnrollmentStatus::Passed->value])
            ->with('certification')
            ->get();

        return view('meeting.empty-state', [
            'enrollments' => $enrollments ?? collect(),
        ]);
    }

    /**
     * 受講生の予約申請。
     */
    public function store(Enrollment $enrollment, StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $meeting = $action(
            $enrollment,
            Carbon::parse($request->validated('scheduled_at')),
            $request->validated('topic'),
        );

        return redirect()
            ->route('meetings.show', $meeting)
            ->with('success', '面談を予約しました。');
    }

    /**
     * 当事者(受講生 or コーチ)による面談キャンセル。
     */
    public function cancel(Meeting $meeting, CancelAction $action): RedirectResponse
    {
        $this->authorize('cancel', $meeting);

        $action($meeting, auth()->user());

        return redirect()
            ->route('meetings.show', $meeting)
            ->with('success', '面談をキャンセルしました。面談回数を返却しました。');
    }

    /**
     * 担当コーチによる面談メモ作成・更新。
     */
    public function upsertMemo(Meeting $meeting, UpsertMemoRequest $request, UpsertMemoAction $action): RedirectResponse
    {
        $action($meeting, $request->validated('body'));

        return redirect()
            ->route('meetings.show', $meeting)
            ->with('success', '面談メモを保存しました。');
    }

    /**
     * 予約画面が呼ぶ空き枠取得 JSON エンドポイント。
     */
    public function fetchAvailability(Enrollment $enrollment, AvailabilityRequest $request, FetchAvailabilityAction $action): JsonResponse
    {
        $date = Carbon::parse($request->validated('date'));
        $slots = $action($enrollment, $date);

        return response()->json([
            'date' => $date->toDateString(),
            'slots' => $slots->map(fn (array $slot) => [
                'slot_start' => $slot['slot_start']->toIso8601String(),
                'slot_end' => $slot['slot_end']->toIso8601String(),
                'available_coach_count' => $slot['available_coach_count'],
            ])->all(),
        ]);
    }
}
