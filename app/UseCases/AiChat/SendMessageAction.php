<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Exceptions\AiChat\AiChatDailyLimitExceededException;
use App\Exceptions\AiChat\AiChatUnavailableException;
use App\Exceptions\AiChat\GeminiRequestFailedException;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\Services\AiChatDailyLimiter;
use App\Services\AiChatPromptBuilder;
use App\Services\Contracts\GeminiServiceContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AI 相談のメッセージ送信(同期)。
 *
 * 1. API キー未設定なら Gemini を呼ばずに、利用できない旨を AI 側のエラーとして保存する(回数に数えない)
 *    日次上限超過なら、何も保存せずに止める
 * 2. 受講生の質問と、応答待ち(pending)の AI メッセージを保存し、送信回数を数える
 * 3. Gemini を呼び、成功なら AI メッセージを completed、失敗なら error で保存する(質問は残る)
 * 4. 最初の AI 応答が成功したときに、会話タイトルを AI で 1 回だけ付け直す
 */
final class SendMessageAction
{
    /** 仮タイトル(最初の質問の先頭)の文字数 */
    private const PROVISIONAL_TITLE_LENGTH = 30;

    public function __construct(
        private readonly GeminiServiceContract $gemini,
        private readonly AiChatPromptBuilder $promptBuilder,
        private readonly AiChatDailyLimiter $limiter,
    ) {}

    /**
     * @return array{user_message: AiChatMessage, assistant_message: AiChatMessage, failure: ?GeminiRequestFailedException}
     *
     * @throws AiChatUnavailableException
     * @throws AiChatDailyLimitExceededException
     */
    public function __invoke(AiChatConversation $conversation, string $content): array
    {
        $conversation->loadMissing('user');

        // API キー未設定: Gemini を呼ばず、回数にも数えず、利用できない旨を AI 側のエラーとして残す
        // 提供済み JS は status=error の AI メッセージの content をそのまま表示するため、JS を変更せずに案内できる
        if ($this->isUnavailable()) {
            [$question, $answer] = $this->saveExchange($conversation, $content);
            $answer->update([
                'status' => AiChatMessageStatus::Error,
                'content' => AiChatUnavailableException::MESSAGE,
                'error_detail' => 'GEMINI_API_KEY が未設定のため、Gemini を呼び出していません。',
            ]);

            return ['user_message' => $question, 'assistant_message' => $answer, 'failure' => null];
        }

        $this->limiter->ensureNotExceeded($conversation->user);

        [$question, $answer] = $this->saveExchange($conversation, $content);

        // AI 応答の成否にかかわらず数える
        $this->limiter->hit($conversation->user);

        $prompt = $this->promptBuilder->build($conversation, $question);

        try {
            $response = $this->gemini->generate($prompt['system'], $prompt['contents']);
        } catch (GeminiRequestFailedException $e) {
            $answer->update([
                'status' => AiChatMessageStatus::Error,
                'model' => config('ai-chat.gemini.model'),
                'error_detail' => $e->getMessage(),
            ]);

            Log::channel('ai-chat')->warning('AI 応答の取得に失敗しました。', [
                'conversation_id' => $conversation->id,
                'message_id' => $answer->id,
                'upstream_status' => $e->upstreamStatus,
                'error' => $e->getMessage(),
            ]);

            return ['user_message' => $question, 'assistant_message' => $answer, 'failure' => $e];
        }

        $answer->update([
            'status' => AiChatMessageStatus::Completed,
            'content' => $response->text,
            'model' => $response->model,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'response_time_ms' => $response->responseTimeMs,
        ]);
        $conversation->update(['last_message_at' => now()]);

        $this->refreshTitleIfFirstAnswer($conversation, $question, $answer);

        return ['user_message' => $question, 'assistant_message' => $answer, 'failure' => null];
    }

    /**
     * 送信できる状態か(API キー・日次上限)を確認する。新しい相談モーダルで、会話を作る前の確認にも使う。
     *
     * @throws AiChatUnavailableException
     * @throws AiChatDailyLimitExceededException
     */
    public function ensureSendable(User $user): void
    {
        if ($this->isUnavailable()) {
            throw new AiChatUnavailableException;
        }

        $this->limiter->ensureNotExceeded($user);
    }

    private function isUnavailable(): bool
    {
        return blank(config('ai-chat.gemini.api_key'));
    }

    /**
     * 受講生の質問と、応答待ち(pending)の AI メッセージを保存する。
     * 最初の質問であれば、仮タイトルとして質問の先頭を使う。
     *
     * @return array{0: AiChatMessage, 1: AiChatMessage}
     */
    private function saveExchange(AiChatConversation $conversation, string $content): array
    {
        return DB::transaction(function () use ($conversation, $content) {
            $isFirstMessage = ! $conversation->messages()->exists();

            $question = $conversation->messages()->create([
                'role' => AiChatMessageRole::User,
                'status' => AiChatMessageStatus::Completed,
                'content' => $content,
            ]);
            $answer = $conversation->messages()->create([
                'role' => AiChatMessageRole::Assistant,
                'status' => AiChatMessageStatus::Pending,
                'content' => '',
            ]);

            $attributes = ['last_message_at' => now()];
            if ($isFirstMessage && ! $conversation->title_edited_by_user) {
                $attributes['title'] = Str::limit($content, self::PROVISIONAL_TITLE_LENGTH);
            }
            $conversation->update($attributes);

            return [$question, $answer];
        });
    }

    /**
     * 最初の AI 応答が成功したときに 1 回だけ、会話の内容に合わせたタイトルを AI に付けてもらう。
     * 無効化スイッチ(config: ai-chat.auto_title)が OFF、または受講生が手動で編集した会話では行わない。
     * タイトル生成に失敗しても、相談の応答には影響させない(仮タイトルのまま)。
     */
    private function refreshTitleIfFirstAnswer(AiChatConversation $conversation, AiChatMessage $question, AiChatMessage $answer): void
    {
        if (! config('ai-chat.auto_title') || $conversation->title_edited_by_user) {
            return;
        }

        $completedAnswers = $conversation->messages()
            ->where('role', AiChatMessageRole::Assistant->value)
            ->where('status', AiChatMessageStatus::Completed->value)
            ->count();

        if ($completedAnswers !== 1) {
            return;
        }

        try {
            $response = $this->gemini->generate(
                '次の学習相談の内容を表す、30 文字以内の日本語のタイトルを 1 つだけ出力してください。かぎかっこや記号、説明文は付けないでください。',
                [['role' => 'user', 'text' => "質問:\n{$question->content}\n\n回答:\n{$answer->content}"]],
            );
        } catch (GeminiRequestFailedException $e) {
            Log::channel('ai-chat')->info('会話タイトルの自動生成に失敗しました。', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $title = Str::limit(trim(strtok($response->text, "\n")), 100, '');

        if ($title !== '') {
            $conversation->update(['title' => $title]);
        }
    }
}
