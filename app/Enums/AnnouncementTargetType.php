<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * お知らせの配信対象タイプ。ラベルは要件シートの表記(全受講生 / 資格指定 / ユーザー指定)に合わせる。
 */
enum AnnouncementTargetType: string
{
    case AllStudents = 'all_students';
    case Certification = 'certification';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::AllStudents => '全受講生',
            self::Certification => '資格指定',
            self::User => 'ユーザー指定',
        };
    }
}
