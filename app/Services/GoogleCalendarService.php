<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Mentoring\GoogleCalendarCredentialRevokedException;
use App\Exceptions\Mentoring\GoogleCalendarRequestFailedException;
use App\Models\GoogleCalendarCredential;
use App\Models\Meeting;
use App\Services\Contracts\GoogleCalendarServiceContract;
use Carbon\Carbon;
use Google\Client;
use Google\Exception as GoogleException;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\FreeBusyRequest;
use Google\Service\Exception as GoogleServiceException;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Google Calendar API を呼び出す GoogleCalendarServiceContract の実装。
 *
 * - アクセストークンの期限切れ時は refresh token で更新し、連携情報に保存する
 * - refresh token が無効(invalid_grant)なら連携情報を削除し、GoogleCalendarCredentialRevokedException を投げる
 * - それ以外の通信失敗は GoogleCalendarRequestFailedException に変換する(連携情報は残す)
 */
final class GoogleCalendarService implements GoogleCalendarServiceContract
{
    /** 予約画面の表示を待たせすぎないための通信タイムアウト(秒) */
    private const TIMEOUT_SECONDS = 5;

    /** 期限ぎりぎりのトークンで失敗しないよう、この秒数前から期限切れとみなす */
    private const EXPIRY_MARGIN_SECONDS = 60;

    public function authorizationUrl(string $state): string
    {
        $client = $this->makeClient();
        $client->setState($state);

        return $client->createAuthUrl();
    }

    public function exchangeCode(string $code): array
    {
        $client = $this->makeClient();

        try {
            $token = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error']) || empty($token['refresh_token'])) {
                throw new GoogleCalendarRequestFailedException('認可コードをトークンに交換できませんでした。');
            }

            // calendar_id はメールアドレスと決めつけず、メインカレンダーの実際の ID を取得する(PM 確認 Q6)
            $calendarId = (new Calendar($client))->calendars->get('primary')->getId();
        } catch (GoogleException|GuzzleException $e) {
            throw new GoogleCalendarRequestFailedException(previous: $e);
        }

        return [
            'calendar_id' => $calendarId,
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'],
            'token_expires_at' => now()->addSeconds((int) $token['expires_in']),
        ];
    }

    public function busyIntervals(GoogleCalendarCredential $credential, Carbon $from, Carbon $to): array
    {
        return $this->call($credential, function (Calendar $calendar) use ($credential, $from, $to) {
            $request = new FreeBusyRequest([
                'timeMin' => $from->toRfc3339String(),
                'timeMax' => $to->toRfc3339String(),
                'timeZone' => config('app.timezone'),
                'items' => [['id' => $credential->calendar_id]],
            ]);

            $result = $calendar->freebusy->query($request)->getCalendars()[$credential->calendar_id] ?? null;

            if ($result === null || ! empty($result->getErrors())) {
                throw new GoogleCalendarRequestFailedException('予定あり時間を取得できませんでした。');
            }

            return array_map(fn ($period) => [
                'start' => Carbon::parse($period->getStart()),
                'end' => Carbon::parse($period->getEnd()),
            ], $result->getBusy());
        });
    }

    public function createEvent(GoogleCalendarCredential $credential, Meeting $meeting): string
    {
        $meeting->loadMissing(['student', 'enrollment.certification']);

        $event = new Event([
            'summary' => sprintf(
                '【Certify LMS】面談：%s（%s）',
                $meeting->student->name,
                $meeting->enrollment->certification->name,
            ),
            'description' => implode("\n", [
                '面談URL：'.($meeting->meeting_url_snapshot ?? ''),
                '相談内容：'.$meeting->topic,
                '面談詳細：'.route('meetings.show', $meeting),
            ]),
            'start' => [
                'dateTime' => $meeting->scheduled_at->toRfc3339String(),
                'timeZone' => config('app.timezone'),
            ],
            'end' => [
                'dateTime' => $meeting->scheduled_at->copy()->addHour()->toRfc3339String(),
                'timeZone' => config('app.timezone'),
            ],
        ]);

        return $this->call(
            $credential,
            fn (Calendar $calendar) => $calendar->events->insert($credential->calendar_id, $event)->getId(),
        );
    }

    public function deleteEvent(GoogleCalendarCredential $credential, string $eventId): void
    {
        $this->call($credential, function (Calendar $calendar) use ($credential, $eventId) {
            try {
                $calendar->events->delete($credential->calendar_id, $eventId);
            } catch (GoogleServiceException $e) {
                // コーチが Google 側で先に予定を消していた場合(404 / 410)は、削除済みとして成功扱いにする
                if (! in_array($e->getCode(), [404, 410], true)) {
                    throw $e;
                }
            }
        });
    }

    /**
     * 有効なトークンをセットした Calendar を用意して処理を実行し、失敗を 2 種類の例外に振り分ける。
     *
     * @template T
     *
     * @param callable(Calendar): T $callback
     *
     * @return T
     */
    private function call(GoogleCalendarCredential $credential, callable $callback): mixed
    {
        try {
            $client = $this->makeClient();
            $client->setAccessToken($this->validAccessToken($credential));

            return $callback(new Calendar($client));
        } catch (GoogleException|GuzzleException $e) {
            throw new GoogleCalendarRequestFailedException(previous: $e);
        }
    }

    /**
     * 期限内ならそのまま、期限切れなら refresh token で更新したアクセストークンを返す。
     *
     * @throws GoogleCalendarCredentialRevokedException refresh token が無効(連携は削除済み)
     */
    private function validAccessToken(GoogleCalendarCredential $credential): string
    {
        if ($credential->token_expires_at->subSeconds(self::EXPIRY_MARGIN_SECONDS)->isFuture()) {
            return $credential->access_token;
        }

        $token = $this->makeClient()->fetchAccessTokenWithRefreshToken($credential->refresh_token);

        // 一時的な通信エラーと区別し、認証情報の無効が確認できたときだけ未連携に戻す(PM 確認 Q3)
        if (($token['error'] ?? null) === 'invalid_grant') {
            $credential->delete();

            throw new GoogleCalendarCredentialRevokedException;
        }

        if (isset($token['error'])) {
            throw new GoogleCalendarRequestFailedException('アクセストークンを更新できませんでした。');
        }

        $credential->update([
            'access_token' => $token['access_token'],
            'token_expires_at' => now()->addSeconds((int) $token['expires_in']),
        ]);

        return $token['access_token'];
    }

    private function makeClient(): Client
    {
        $client = new Client;
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect_uri'));
        $client->setScopes([Calendar::CALENDAR_READONLY, Calendar::CALENDAR_EVENTS]);
        // refresh token を受け取るため、オフラインアクセスと毎回の同意画面表示を指定する
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setHttpClient(new HttpClient(['timeout' => self::TIMEOUT_SECONDS]));

        return $client;
    }
}
