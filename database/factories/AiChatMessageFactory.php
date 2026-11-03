<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 既定は受講生の発言(completed)。AI の応答は assistant()、失敗した応答は error() を使う。
 *
 * @extends Factory<AiChatMessage>
 */
class AiChatMessageFactory extends Factory
{
    protected $model = AiChatMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ai_chat_conversation_id' => AiChatConversation::factory(),
            'role' => AiChatMessageRole::User,
            'status' => AiChatMessageStatus::Completed,
            'content' => fake()->realText(60),
        ];
    }

    public function assistant(): static
    {
        return $this->state(fn () => [
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Completed,
            'content' => fake()->realText(200),
            'model' => config('ai-chat.gemini.model'),
            'input_tokens' => fake()->numberBetween(100, 2000),
            'output_tokens' => fake()->numberBetween(50, 800),
            'response_time_ms' => fake()->numberBetween(800, 8000),
        ]);
    }

    public function error(): static
    {
        return $this->state(fn () => [
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Error,
            'content' => '',
            'model' => config('ai-chat.gemini.model'),
            'error_detail' => 'HTTP 503: This model is currently experiencing high demand.',
        ]);
    }
}
