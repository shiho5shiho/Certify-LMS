<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Exceptions\Mentoring\GoogleCalendarCredentialRevokedException;
use App\Exceptions\Mentoring\GoogleCalendarRequestFailedException;
use App\Models\GoogleCalendarCredential;
use App\Models\Meeting;
use Carbon\Carbon;

/**
 * コーチの Google カレンダーとの通信(OAuth 認可 / 予定あり時間の取得 / 予定の登録・削除)の契約。
 *
 * Google API の呼び出しはすべてこの契約の実装に閉じ込める。
 * テスト(T-A-04)ではこの契約をモックに差し替えて、外部通信なしで検証する。
 */
interface GoogleCalendarServiceContract
{
    /**
     * Google の認可画面の URL を返す。state はなりすまし防止用にセッションへ保存した値を渡す。
     */
    public function authorizationUrl(string $state): string;

    /**
     * 認可コードをトークンに交換し、メインカレンダーの ID と合わせて返す。
     *
     * @return array{calendar_id: string, access_token: string, refresh_token: string, token_expires_at: Carbon}
     *
     * @throws GoogleCalendarRequestFailedException
     */
    public function exchangeCode(string $code): array;

    /**
     * 指定期間内の「予定あり」の時間帯を返す(Google 側で「予定なし」にした予定は含まない)。
     *
     * @return list<array{start: Carbon, end: Carbon}>
     *
     * @throws GoogleCalendarRequestFailedException
     * @throws GoogleCalendarCredentialRevokedException
     */
    public function busyIntervals(GoogleCalendarCredential $credential, Carbon $from, Carbon $to): array;

    /**
     * 面談の予定をコーチのカレンダーに登録し、イベント ID を返す。
     *
     * @throws GoogleCalendarRequestFailedException
     * @throws GoogleCalendarCredentialRevokedException
     */
    public function createEvent(GoogleCalendarCredential $credential, Meeting $meeting): string;

    /**
     * コーチのカレンダーから予定を削除する。Google 側で既に削除済みの場合も成功として扱う。
     *
     * @throws GoogleCalendarRequestFailedException
     * @throws GoogleCalendarCredentialRevokedException
     */
    public function deleteEvent(GoogleCalendarCredential $credential, string $eventId): void;
}
