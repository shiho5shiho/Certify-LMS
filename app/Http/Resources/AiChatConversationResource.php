<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AiChatConversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * AI 相談の会話の JSON。ウィジェット(会話 ID の保持)と、フル画面のタイトル更新表示で使う。
 *
 * @mixin AiChatConversation
 */
class AiChatConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
        ];
    }
}
