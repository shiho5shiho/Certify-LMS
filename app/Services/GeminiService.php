<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AiChat\GeminiRequestFailedException;
use App\Services\Contracts\GeminiServiceContract;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gemini API(generateContent)を呼び出す GeminiServiceContract の実装。
 *
 * - API キーは公式ドキュメントのとおり x-goog-api-key ヘッダーで渡す
 * - タイムアウト(config: ai-chat.gemini.timeout)や Gemini 側のエラー応答は GeminiRequestFailedException に変換する
 * - 5xx 系のエラーのときだけ、1 秒後に 1 回だけ再試行する(429・4xx・タイムアウトは再試行しない)
 * - 思考過程(thought)の部分は除き、応答本文のテキストのみを返す
 */
final class GeminiService implements GeminiServiceContract
{
    /** 再試行を含めた最大の送信回数(1 回目 + 再試行 1 回) */
    private const MAX_ATTEMPTS = 2;

    /** 再試行までの待ち時間(ミリ秒) */
    private const RETRY_DELAY_MS = 1000;

    public function generate(string $systemInstruction, array $contents): GeminiResponse
    {
        $model = (string) config('ai-chat.gemini.model');
        $url = sprintf(
            '%s/models/%s:generateContent',
            rtrim((string) config('ai-chat.gemini.base_url'), '/'),
            $model,
        );

        $startedAt = hrtime(true);

        try {
            $response = Http::withHeaders(['x-goog-api-key' => (string) config('ai-chat.gemini.api_key')])
                ->acceptJson()
                ->timeout((int) config('ai-chat.gemini.timeout'))
                ->retry(
                    self::MAX_ATTEMPTS,
                    fn (int $attempt, ?Exception $e = null) => $this->logRetry($attempt, $e),
                    // 5xx 系のときだけ再試行する。429・4xx・タイムアウトは再試行しない
                    fn (Exception $e) => $e instanceof RequestException && $e->response->serverError(),
                    // 再試行しても失敗したときは例外にせず、これまでどおり下の failed() の分岐でエラーにする
                    throw: false,
                )
                ->post($url, [
                    'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
                    'contents' => array_map(fn (array $content) => [
                        'role' => $content['role'],
                        'parts' => [['text' => $content['text']]],
                    ], $contents),
                ]);
        } catch (ConnectionException $e) {
            throw new GeminiRequestFailedException('Gemini API に接続できませんでした(タイムアウト等)。', null, $e);
        }

        $responseTimeMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        if ($response->failed()) {
            throw new GeminiRequestFailedException(
                sprintf('HTTP %d: %s', $response->status(), (string) $response->json('error.message', '')),
                $response->status(),
            );
        }

        $text = collect($response->json('candidates.0.content.parts', []))
            ->reject(fn (array $part) => ($part['thought'] ?? false) === true)
            ->pluck('text')
            ->filter()
            ->implode('');

        if (trim($text) === '') {
            throw new GeminiRequestFailedException(sprintf(
                '応答本文が空でした(finishReason: %s)。',
                (string) $response->json('candidates.0.finishReason', 'unknown'),
            ));
        }

        return new GeminiResponse(
            text: $text,
            model: (string) $response->json('modelVersion', $model),
            inputTokens: $response->json('usageMetadata.promptTokenCount'),
            outputTokens: $response->json('usageMetadata.candidatesTokenCount'),
            responseTimeMs: $responseTimeMs,
        );
    }

    /**
     * 再試行したことを AI チャットのログに残し、再試行までの待ち時間を返す。
     *
     * Laravel の retry() は、実際に再試行するときだけ待ち時間を問い合わせるため、ここでログを書く。
     * 既存の失敗ログ(SendMessageAction)とは文面・レベルを分ける。
     */
    private function logRetry(int $attempt, ?Exception $e): int
    {
        Log::channel('ai-chat')->info('Gemini API が一時的なエラーを返したため、再試行します。', [
            'attempt' => $attempt,
            'upstream_status' => $e instanceof RequestException ? $e->response->status() : null,
        ]);

        return self::RETRY_DELAY_MS;
    }
}
