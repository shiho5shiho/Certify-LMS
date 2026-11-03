<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChat;

use App\Models\AiChatConversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * AI 相談の会話作成リクエスト。
 *
 * - ウィジェット: source=widget(教材画面では section_id 付き)を JSON で送る
 * - フル画面の「新しい相談」モーダル: source=full-screen と任意の最初の質問(message)をフォームで送る
 */
class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AiChatConversation::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'source' => ['required', 'string', Rule::in(['widget', 'full-screen'])],
            'section_id' => ['nullable', 'string', 'exists:sections,id'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'source' => '相談の開始元',
            'section_id' => '教材',
            'message' => '最初の質問',
        ];
    }
}
