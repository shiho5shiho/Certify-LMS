<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\Services\MeetingQuotaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 受講生本人の面談一覧。filter (upcoming/past/all) クエリで履歴を切り替える。
 */
final class IndexAction
{
    public function __construct(
        private readonly MeetingQuotaService $meetingQuota,
    ) {}

    /**
     * @return array{meetings: LengthAwarePaginator, meetingsRemaining: int}
     */
    public function __invoke(User $student, string $filter): array
    {
        $query = Meeting::query()
            ->with(['enrollment.certification', 'coach'])
            ->forStudent($student)
            ->orderByDesc('scheduled_at');

        $meetings = match ($filter) {
            'past' => $query->past()->paginate(20),
            'all' => $query->paginate(20),
            default => $query->upcoming()->paginate(20),
        };

        return [
            'meetings' => $meetings,
            'meetingsRemaining' => $this->meetingQuota->remaining($student),
        ];
    }
}
