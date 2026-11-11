<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Exceptions\AiChat\GeminiRequestFailedException;
use App\Services\GeminiResponse;

/**
 * Gemini API との通信の契約。
 *
 * Gemini の呼び出しはすべてこの契約の実装に閉じ込める。
 * テストではこの契約をモックに差し替えて、外部通信なしで検証する。
 */
interface GeminiServiceContract
{
    /**
     * システムへの指示と会話の内容を渡し、AI の応答を 1 件受け取る(同期)。
     *
     * @param list<array{role: 'user'|'model', text: string}> $contents 古い順。最後の要素が今回の質問
     *
     * @throws GeminiRequestFailedException
     */
    public function generate(string $systemInstruction, array $contents): GeminiResponse;
}
