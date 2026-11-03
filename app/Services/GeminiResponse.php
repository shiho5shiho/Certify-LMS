<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Gemini の応答本文と、運用観測用のメタデータ(モデル名 / トークン数 / 応答時間)。
 */
final class GeminiResponse
{
    public function __construct(
        public readonly string $text,
        public readonly string $model,
        public readonly ?int $inputTokens,
        public readonly ?int $outputTokens,
        public readonly int $responseTimeMs,
    ) {}
}
