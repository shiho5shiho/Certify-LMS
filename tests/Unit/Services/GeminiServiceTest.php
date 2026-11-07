<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\AiChat\GeminiRequestFailedException;
use App\Services\GeminiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * GeminiService のテスト。
 * Gemini との通信は Http::fake() で差し替え、再試行の待ち時間は Sleep::fake() で飛ばす。
 */
#[Group('external-api')]
class GeminiServiceTest extends TestCase
{
    private const GEMINI_URL = 'generativelanguage.googleapis.com/*';

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
        config([
            'ai-chat.gemini.api_key' => 'test-api-key',
            'ai-chat.gemini.model' => 'gemini-test',
            'ai-chat.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        ]);
    }

    public function test_sends_system_instruction_and_contents_with_api_key(): void
    {
        Http::fake([self::GEMINI_URL => Http::response($this->geminiResponse('回答です。'))]);

        app(GeminiService::class)->generate('あなたは学習アシスタントです。', [
            ['role' => 'user', 'text' => '二分探索木とは？'],
            ['role' => 'model', 'text' => '左が小さい二分木です。'],
            ['role' => 'user', 'text' => '平均の比較回数は？'],
        ]);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent'
            && $request->hasHeader('x-goog-api-key', 'test-api-key')
            && $request['systemInstruction'] === ['parts' => [['text' => 'あなたは学習アシスタントです。']]]
            && $request['contents'] === [
                ['role' => 'user', 'parts' => [['text' => '二分探索木とは？']]],
                ['role' => 'model', 'parts' => [['text' => '左が小さい二分木です。']]],
                ['role' => 'user', 'parts' => [['text' => '平均の比較回数は？']]],
            ]);
    }

    public function test_returns_answer_without_thought_parts(): void
    {
        Http::fake([self::GEMINI_URL => Http::response([
            'candidates' => [['content' => ['role' => 'model', 'parts' => [
                ['text' => '考え中の内容', 'thought' => true],
                ['text' => '回答です。'],
            ]], 'finishReason' => 'STOP']],
            'usageMetadata' => ['promptTokenCount' => 120, 'candidatesTokenCount' => 45],
            'modelVersion' => 'gemini-test-001',
        ])]);

        $response = app(GeminiService::class)->generate('system', [['role' => 'user', 'text' => '質問']]);

        $this->assertSame('回答です。', $response->text);
        $this->assertSame('gemini-test-001', $response->model);
        $this->assertSame(120, $response->inputTokens);
        $this->assertSame(45, $response->outputTokens);
    }

    public function test_retries_once_after_server_error_and_succeeds(): void
    {
        Http::fake([self::GEMINI_URL => Http::sequence()
            ->push(['error' => ['message' => 'high demand']], 503)
            ->push($this->geminiResponse('再試行後の回答です。'))]);
        Log::shouldReceive('channel')->once()->with('ai-chat')->andReturnSelf();
        Log::shouldReceive('info')->once()->withArgs(
            fn (string $message, array $context) => $message === 'Gemini API が一時的なエラーを返したため、再試行します。'
                && $context['upstream_status'] === 503,
        );

        $response = app(GeminiService::class)->generate('system', [['role' => 'user', 'text' => '質問']]);

        $this->assertSame('再試行後の回答です。', $response->text);
        Http::assertSentCount(2);
        // 1 秒待ってから再試行する
        Sleep::assertSequence([Sleep::for(1)->second()]);
    }

    public function test_throws_when_server_error_continues_after_retry(): void
    {
        Http::fake([self::GEMINI_URL => Http::response(['error' => ['message' => 'internal error']], 500)]);

        $e = $this->catchFailure(fn () => app(GeminiService::class)->generate('system', [['role' => 'user', 'text' => '質問']]));

        $this->assertSame(500, $e->upstreamStatus);
        // 再試行は 1 回だけ(合計 2 回まで)
        Http::assertSentCount(2);
    }

    #[DataProvider('noRetryStatusProvider')]
    public function test_does_not_retry_on_too_many_requests_or_client_error(int $status): void
    {
        Http::fake([self::GEMINI_URL => Http::response(['error' => ['message' => 'error']], $status)]);
        Log::shouldReceive('channel')->never();

        $e = $this->catchFailure(fn () => app(GeminiService::class)->generate('system', [['role' => 'user', 'text' => '質問']]));

        $this->assertSame($status, $e->upstreamStatus);
        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
    }

    /**
     * @return array<string, array{int}>
     */
    public static function noRetryStatusProvider(): array
    {
        return [
            '429 Too Many Requests' => [429],
            '400 Bad Request' => [400],
            '401 Unauthorized' => [401],
            '403 Forbidden' => [403],
        ];
    }

    public function test_does_not_retry_on_timeout(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('Operation timed out');
        });

        $e = $this->catchFailure(fn () => app(GeminiService::class)->generate('system', [['role' => 'user', 'text' => '質問']]));

        $this->assertNull($e->upstreamStatus);
        $this->assertSame(1, $attempts);
        Sleep::assertNeverSlept();
    }

    /**
     * 処理を実行し、GeminiRequestFailedException が投げられたことを確かめて、その例外を返す。
     */
    private function catchFailure(callable $callback): GeminiRequestFailedException
    {
        try {
            $callback();
        } catch (GeminiRequestFailedException $e) {
            return $e;
        }

        $this->fail('GeminiRequestFailedException が投げられませんでした。');
    }

    /**
     * @return array<string, mixed>
     */
    private function geminiResponse(string $text): array
    {
        return [
            'candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $text]]], 'finishReason' => 'STOP']],
            'usageMetadata' => ['promptTokenCount' => 120, 'candidatesTokenCount' => 45],
            'modelVersion' => 'gemini-test',
        ];
    }
}
