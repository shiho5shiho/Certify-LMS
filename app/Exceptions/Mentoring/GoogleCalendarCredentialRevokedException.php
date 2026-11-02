<?php

declare(strict_types=1);

namespace App\Exceptions\Mentoring;

use RuntimeException;

/**
 * Google 側で連携が取り消され、認証情報(refresh token)が無効になった場合の例外。
 *
 * 投げる時点で連携情報は削除済み(コーチは未連携に戻る)。
 * 画面には出さないため HTTP 例外にはしない。
 */
final class GoogleCalendarCredentialRevokedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Google カレンダーの認証情報が無効になったため、連携を解除しました。');
    }
}
