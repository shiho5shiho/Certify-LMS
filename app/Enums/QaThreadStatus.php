<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 質問掲示板スレッドの解決状態を表す Enum。
 */
enum QaThreadStatus: string
{
    case Unresolved = 'unresolved';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Unresolved => '未解決',
            self::Resolved => '解決済',
        };
    }
}
