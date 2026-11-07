<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\Mentoring\GoogleCalendarCredentialRevokedException;
use App\Exceptions\Mentoring\GoogleCalendarRequestFailedException;
use App\Models\GoogleCalendarCredential;
use App\Models\Meeting;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;
use Throwable;

/**
 * GoogleCalendarService のテスト。
 * Google との通信は、決めた応答を順番に返す Guzzle の MockHandler に差し替える。
 */
#[Group('external-api')]
class GoogleCalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array<string, mixed>> 送ったリクエストの記録 */
    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect_uri' => 'https://lms.test/settings/google-calendar/callback',
        ]);
        $this->freezeTime();
    }

    public function test_authorization_url_contains_state_and_offline_access(): void
    {
        $url = $this->service([])->authorizationUrl('state-123');

        $this->assertStringContainsString('state=state-123', $url);
        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('prompt=consent', $url);
        $this->assertCount(0, $this->history);
    }

    public function test_exchange_code_returns_token_and_primary_calendar_id(): void
    {
        $service = $this->service([
            $this->jsonResponse(['access_token' => 'new-access-token', 'refresh_token' => 'new-refresh-token', 'expires_in' => 3600, 'token_type' => 'Bearer']),
            $this->jsonResponse(['id' => 'coach@example.com']),
        ]);

        $token = $service->exchangeCode('auth-code');

        $this->assertSame('coach@example.com', $token['calendar_id']);
        $this->assertSame('new-access-token', $token['access_token']);
        $this->assertSame('new-refresh-token', $token['refresh_token']);
        $this->assertSame(now()->addSeconds(3600)->timestamp, $token['token_expires_at']->timestamp);
        $this->assertStringContainsString('code=auth-code', (string) $this->history[0]['request']->getBody());
        $this->assertStringEndsWith('/calendars/primary', $this->history[1]['request']->getUri()->getPath());
    }

    public function test_exchange_code_fails_without_refresh_token(): void
    {
        $service = $this->service([
            $this->jsonResponse(['access_token' => 'new-access-token', 'expires_in' => 3600]),
        ]);

        $this->assertThrowsException(GoogleCalendarRequestFailedException::class, fn () => $service->exchangeCode('auth-code'));

        // カレンダー ID の取得には進まない
        $this->assertCount(1, $this->history);
    }

    public function test_exchange_code_fails_when_google_returns_error(): void
    {
        $service = $this->service([
            $this->jsonResponse(['error' => 'invalid_grant', 'error_description' => 'Bad Request'], 400),
        ]);

        $this->assertThrowsException(GoogleCalendarRequestFailedException::class, fn () => $service->exchangeCode('used-code'));
    }

    public function test_busy_intervals_returns_busy_periods_with_valid_token(): void
    {
        $credential = GoogleCalendarCredential::factory()->create(['token_expires_at' => now()->addHour()]);
        $service = $this->service([
            $this->jsonResponse(['calendars' => [$credential->calendar_id => ['busy' => [
                ['start' => '2026-11-09T10:00:00+09:00', 'end' => '2026-11-09T11:00:00+09:00'],
            ]]]]),
        ]);

        $busy = $service->busyIntervals($credential, Carbon::parse('2026-11-09'), Carbon::parse('2026-11-10'));

        $this->assertCount(1, $busy);
        $this->assertTrue($busy[0]['start']->equalTo(Carbon::parse('2026-11-09T10:00:00+09:00')));
        $this->assertTrue($busy[0]['end']->equalTo(Carbon::parse('2026-11-09T11:00:00+09:00')));
        // 期限内のトークンはそのまま使い、更新の通信はしない
        $this->assertCount(1, $this->history);
        $this->assertSame('Bearer dummy-access-token', $this->history[0]['request']->getHeaderLine('Authorization'));
    }

    public function test_busy_intervals_fails_when_calendar_has_errors(): void
    {
        $credential = GoogleCalendarCredential::factory()->create();
        $service = $this->service([
            $this->jsonResponse(['calendars' => [$credential->calendar_id => ['errors' => [['domain' => 'global', 'reason' => 'notFound']]]]]),
        ]);

        $this->assertThrowsException(GoogleCalendarRequestFailedException::class, fn () => $service->busyIntervals($credential, now(), now()->addDay()));
    }

    public function test_expired_token_is_refreshed_saved_and_used(): void
    {
        $credential = GoogleCalendarCredential::factory()->create(['token_expires_at' => now()->subMinute()]);
        $service = $this->service([
            $this->jsonResponse(['access_token' => 'refreshed-access-token', 'expires_in' => 3600, 'token_type' => 'Bearer']),
            $this->jsonResponse(['calendars' => [$credential->calendar_id => ['busy' => []]]]),
        ]);

        $busy = $service->busyIntervals($credential, now(), now()->addDay());

        $this->assertSame([], $busy);
        $credential->refresh();
        $this->assertSame('refreshed-access-token', $credential->access_token);
        $this->assertSame(now()->addSeconds(3600)->timestamp, $credential->token_expires_at->timestamp);
        $this->assertStringContainsString('grant_type=refresh_token', (string) $this->history[0]['request']->getBody());
        // 更新したトークンでカレンダーを操作する
        $this->assertSame('Bearer refreshed-access-token', $this->history[1]['request']->getHeaderLine('Authorization'));
    }

    public function test_token_expiring_within_60_seconds_is_refreshed(): void
    {
        $credential = GoogleCalendarCredential::factory()->create(['token_expires_at' => now()->addSeconds(60)]);
        $service = $this->service([
            $this->jsonResponse(['access_token' => 'refreshed-access-token', 'expires_in' => 3600]),
            $this->jsonResponse(['calendars' => [$credential->calendar_id => ['busy' => []]]]),
        ]);

        $service->busyIntervals($credential, now(), now()->addDay());

        $this->assertCount(2, $this->history);
    }

    public function test_token_with_more_than_60_seconds_left_is_not_refreshed(): void
    {
        $credential = GoogleCalendarCredential::factory()->create(['token_expires_at' => now()->addSeconds(61)]);
        $service = $this->service([
            $this->jsonResponse(['calendars' => [$credential->calendar_id => ['busy' => []]]]),
        ]);

        $service->busyIntervals($credential, now(), now()->addDay());

        $this->assertCount(1, $this->history);
    }

    public function test_invalid_grant_deletes_credential_and_throws_revoked(): void
    {
        $credential = GoogleCalendarCredential::factory()->create(['token_expires_at' => now()->subMinute()]);
        $service = $this->service([
            $this->jsonResponse(['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], 400),
        ]);

        $this->assertThrowsException(GoogleCalendarCredentialRevokedException::class, fn () => $service->busyIntervals($credential, now(), now()->addDay()));

        $this->assertModelMissing($credential);
        // カレンダーの操作には進まない
        $this->assertCount(1, $this->history);
    }

    public function test_other_refresh_error_keeps_credential(): void
    {
        $credential = GoogleCalendarCredential::factory()->create(['token_expires_at' => now()->subMinute()]);
        $service = $this->service([
            $this->jsonResponse(['error' => 'internal_failure'], 500),
        ]);

        $this->assertThrowsException(GoogleCalendarRequestFailedException::class, fn () => $service->busyIntervals($credential, now(), now()->addDay()));

        $this->assertModelExists($credential);
        $this->assertSame('dummy-access-token', $credential->fresh()->access_token);
    }

    public function test_connection_failure_is_converted_and_keeps_credential(): void
    {
        $credential = GoogleCalendarCredential::factory()->create();
        $service = $this->service([
            new ConnectException('Connection timed out', new Request('POST', 'https://www.googleapis.com/calendar/v3/freeBusy')),
        ]);

        $this->assertThrowsException(GoogleCalendarRequestFailedException::class, fn () => $service->busyIntervals($credential, now(), now()->addDay()));

        $this->assertModelExists($credential);
    }

    public function test_create_event_sends_meeting_details_and_returns_event_id(): void
    {
        $credential = GoogleCalendarCredential::factory()->create();
        $meeting = Meeting::factory()->create(['scheduled_at' => Carbon::parse('2026-11-16 10:00:00')]);
        $service = $this->service([$this->jsonResponse(['id' => 'evt-123'])]);

        $eventId = $service->createEvent($credential, $meeting);

        $this->assertSame('evt-123', $eventId);
        $body = json_decode((string) $this->history[0]['request']->getBody(), true);
        $this->assertSame(
            '【Certify LMS】面談：'.$meeting->student->name.'（'.$meeting->enrollment->certification->name.'）',
            $body['summary'],
        );
        $this->assertSame(Carbon::parse('2026-11-16 10:00:00')->toRfc3339String(), $body['start']['dateTime']);
        $this->assertSame(Carbon::parse('2026-11-16 11:00:00')->toRfc3339String(), $body['end']['dateTime']);
    }

    public function test_delete_event_sends_delete_request(): void
    {
        $credential = GoogleCalendarCredential::factory()->create();
        $service = $this->service([new Response(204)]);

        $service->deleteEvent($credential, 'evt-123');

        $this->assertSame('DELETE', $this->history[0]['request']->getMethod());
        $this->assertStringEndsWith('/events/evt-123', $this->history[0]['request']->getUri()->getPath());
    }

    public function test_delete_event_treats_already_deleted_event_as_success(): void
    {
        foreach ([404, 410] as $status) {
            $credential = GoogleCalendarCredential::factory()->create();
            $service = $this->service([$this->jsonResponse(['error' => ['code' => $status, 'message' => 'Not Found']], $status)]);

            // コーチが Google 側で先に予定を消していても、例外にならない
            $service->deleteEvent($credential, 'evt-123');
        }

        $this->assertCount(2, $this->history);
    }

    public function test_delete_event_fails_on_server_error(): void
    {
        $credential = GoogleCalendarCredential::factory()->create();
        $service = $this->service([$this->jsonResponse(['error' => ['code' => 500, 'message' => 'Backend Error']], 500)]);

        $this->assertThrowsException(GoogleCalendarRequestFailedException::class, fn () => $service->deleteEvent($credential, 'evt-123'));
    }

    /**
     * 決めた応答を順番に返す通信部品を使った GoogleCalendarService を作る。送ったリクエストは $history に記録する。
     *
     * @param array<int, Response|Throwable> $responses
     */
    private function service(array $responses): GoogleCalendarService
    {
        $mock = new MockHandler($responses);

        // 偽の応答を返す直前でリクエストを記録する。
        // Google のライブラリがトークン(Authorization ヘッダー)を付けたあとの、実際に送る内容を確かめるため
        $stack = HandlerStack::create(function (RequestInterface $request, array $options) use ($mock) {
            $this->history[] = ['request' => $request];

            return $mock($request, $options);
        });

        // 本番と同じく http_errors を無効にし、Google のエラー応答を例外ではなく応答として受け取る
        return new GoogleCalendarService(new HttpClient(['handler' => $stack, 'http_errors' => false]));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function jsonResponse(array $body, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
    }

    /**
     * 処理を実行し、指定した例外が投げられたことを確かめる(例外のあとも続けて検証できるようにする)。
     */
    private function assertThrowsException(string $exceptionClass, callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            $this->assertInstanceOf($exceptionClass, $e);

            return;
        }

        $this->fail($exceptionClass.' が投げられませんでした。');
    }
}
