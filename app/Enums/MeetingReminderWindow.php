<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 面談リマインダーの配信タイミング。値は `--window` オプションの値と一致させる。
 */
enum MeetingReminderWindow: string
{
    case Eve = 'eve';
    case OneHourBefore = 'one_hour_before';

    /**
     * 通知のタイトル(PM 確認済み)。
     */
    public function title(): string
    {
        return match ($this) {
            self::Eve => '明日の面談のリマインドです',
            self::OneHourBefore => '面談のリマインドです',
        };
    }

    /**
     * 送信済みを記録する meetings のカラム名。
     */
    public function remindedAtColumn(): string
    {
        return match ($this) {
            self::Eve => 'eve_reminded_at',
            self::OneHourBefore => 'one_hour_reminded_at',
        };
    }
}
