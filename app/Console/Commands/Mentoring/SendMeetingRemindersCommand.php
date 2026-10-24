<?php

declare(strict_types=1);

namespace App\Console\Commands\Mentoring;

use App\Enums\MeetingReminderWindow;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\UseCases\Meeting\SendMeetingReminderAction;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;

/**
 * 予約済の面談にリマインダー(前日 / 1 時間前)を送る Schedule Command。
 *
 * - eve: 毎日 9:00 起動。翌日(0:00〜23:59)に開始する面談が対象
 * - one_hour_before: 30 分間隔で起動。これから 60 分以内(60 分後ちょうどを含む)に開始する面談が対象
 * いずれも未送信のものだけを対象にし、二重配信防止は SendMeetingReminderAction が担う。
 */
class SendMeetingRemindersCommand extends Command
{
    protected $signature = 'notifications:send-meeting-reminders
                            {--window= : 配信タイミング(eve: 前日 / one_hour_before: 1 時間前)}';

    protected $description = '予約済の面談にリマインダー(前日 / 1 時間前)を送る';

    public function handle(SendMeetingReminderAction $action): int
    {
        $window = MeetingReminderWindow::tryFrom((string) $this->option('window'));
        if ($window === null) {
            $this->error('--window には eve または one_hour_before を指定してください。');

            return self::INVALID;
        }

        [$from, $to] = $this->targetRange($window, now());

        $count = 0;

        // 送信日時を書き込むと検索条件(whereNull)から外れるため、
        // ページ番号ずれで取りこぼさないよう id 基準で順に取得する
        $meetings = Meeting::query()
            ->where('status', MeetingStatus::Reserved->value)
            ->whereNull($window->remindedAtColumn())
            ->whereBetween('scheduled_at', [$from, $to])
            ->lazyById(100);

        foreach ($meetings as $meeting) {
            if ($action($meeting, $window)) {
                $count++;
            }
        }

        $this->info("面談リマインダー({$window->value})を {$count} 件送信しました。");

        return self::SUCCESS;
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function targetRange(MeetingReminderWindow $window, CarbonInterface $now): array
    {
        return match ($window) {
            MeetingReminderWindow::Eve => [
                $now->copy()->addDay()->startOfDay(),
                $now->copy()->addDay()->endOfDay(),
            ],
            MeetingReminderWindow::OneHourBefore => [
                $now->copy(),
                $now->copy()->addMinutes(60),
            ],
        };
    }
}
