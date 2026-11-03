<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\AiChatMessageStatus;
use App\Models\AiChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * AI 相談のメッセージの JSON。
 *
 * 運用観測用のメタデータ(モデル名 / トークン数 / 応答時間 / エラー内容)は受講生に見せないため含めない。
 * (提供済みの message-renderer.js は response_time_ms / output_tokens があると表示するため)
 *
 * エラーの AI メッセージは本文が空のため、フル画面の吹き出し(_partials/message-bubble)と同じ文言を content に入れる。
 * ウィジェットの履歴復元で、空の吹き出しが表示されないようにするため。
 *
 * @mixin AiChatMessage
 */
class AiChatMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role->value,
            'status' => $this->status->value,
            'content' => $this->displayContent(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function displayContent(): string
    {
        if ($this->status !== AiChatMessageStatus::Error || $this->content !== '') {
            return $this->content;
        }

        // _partials/message-bubble.blade.php の文言と同じ
        $errorDetail = (string) ($this->error_detail ?? '');

        return match (true) {
            str_contains($errorDetail, '429') => 'Gemini API のリクエスト制限に達しました。1 分ほど待って再試行してください。',
            str_contains($errorDetail, '503') || str_contains($errorDetail, '502') || str_contains($errorDetail, '500') => 'Gemini API が一時的に応答していません。少し待って再試行してください。',
            default => 'AI が応答できませんでした。しばらく時間をおいて再試行してください。',
        };
    }
}
