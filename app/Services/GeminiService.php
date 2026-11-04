<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AiChat\GeminiRequestFailedException;
use App\Services\Contracts\GeminiServiceContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Gemini API(generateContent)を呼び出す GeminiServiceContract の実装。
 *
 * - API キーは公式ドキュメントのとおり x-goog-api-key ヘッダーで渡す
 * - タイムアウト(config: ai-chat.gemini.timeout)や Gemini 側のエラー応答は GeminiRequestFailedException に変換する
 * - 思考過程(thought)の部分は除き、応答本文のテキストのみを返す
 */
final class GeminiService implements GeminiServiceContract
{
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
}
