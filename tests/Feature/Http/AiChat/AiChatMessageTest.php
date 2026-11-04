<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

/**
 * AI 相談のメッセージ送信のテスト(同期応答・失敗時のフォールバック・日次上限・AI に渡す文脈と履歴)。
 * Gemini との通信は Http::fake() で差し替える。
 */
class AiChatMessageTest extends TestCase
{
    use ContentTestHelpers;
    use RefreshDatabase;

    private const GEMINI_URL = 'generativelanguage.googleapis.com/*';

    private User $student;

    private Certification $certification;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'ai-chat.auto_title' => false,
            'ai-chat.daily_limit' => 30,
            'ai-chat.history_limit' => 10,
        ]);

        $this->student = User::factory()->student()->inProgress()->create();
        $this->certification = Certification::factory()->published()->create(['name' => '基本情報技術者試験']);
        Enrollment::factory()->for($this->student, 'user')->for($this->certification)->learning()->create();
        [, , $this->section] = $this->makePartChain($this->certification);
    }

    public function test_sends_message_and_saves_answer_with_metadata(): void
    {
        Http::fake([self::GEMINI_URL => Http::response($this->geminiResponse('左が小さく右が大きい二分木です。'))]);
        $conversation = $this->conversation();

        $this->actingAs($this->student)
            ->postJson($this->messagesUrl($conversation), ['content' => '二分探索木とは？'])
            ->assertOk()
            ->assertJsonPath('user_message.role', 'user')
            ->assertJsonPath('user_message.content', '二分探索木とは？')
            ->assertJsonPath('assistant_message.role', 'assistant')
            ->assertJsonPath('assistant_message.status', 'completed')
            ->assertJsonPath('assistant_message.content', '左が小さく右が大きい二分木です。')
            ->assertJsonPath('conversation.id', $conversation->id);

        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'status' => 'completed',
            'model' => 'gemini-test',
            'input_tokens' => 120,
            'output_tokens' => 45,
        ]);
        Http::assertSent(fn (Request $request) => $request->hasHeader('x-goog-api-key', 'test-api-key'));
    }

    public function test_metadata_is_not_included_in_json(): void
    {
        Http::fake([self::GEMINI_URL => Http::response($this->geminiResponse('回答です。'))]);

        $this->actingAs($this->student)
            ->postJson($this->messagesUrl($this->conversation()), ['content' => '質問です'])
            ->assertOk()
            ->assertJsonMissingPath('assistant_message.model')
            ->assertJsonMissingPath('assistant_message.input_tokens')
            ->assertJsonMissingPath('assistant_message.output_tokens')
            ->assertJsonMissingPath('assistant_message.response_time_ms')
            ->assertJsonMissingPath('assistant_message.error_detail');
    }

    public function test_question_remains_and_answer_is_saved_as_error_when_gemini_fails(): void
    {
        Http::fake([self::GEMINI_URL => Http::response(['error' => ['message' => 'high demand']], 503)]);
        $conversation = $this->conversation();

        $this->actingAs($this->student)
            ->postJson($this->messagesUrl($conversation), ['content' => '二分探索木とは？'])
            ->assertStatus(502)
            ->assertJsonPath('upstream_status', 503)
            ->assertJsonPath('user_message.content', '二分探索木とは？')
            ->assertJsonPath('assistant_message.status', 'error');

        $this->assertDatabaseHas('ai_chat_messages', [
            'ai_chat_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => '二分探索木とは？',
        ]);
        $answer = AiChatMessage::where('role', 'assistant')->firstOrFail();
        $this->assertSame(AiChatMessageStatus::Error, $answer->status);
        $this->assertStringContainsString('503', (string) $answer->error_detail);
    }

    public function test_timeout_is_treated_as_failure(): void
    {
        Http::fake(fn () => throw new ConnectionException('Operation timed out'));

        $this->actingAs($this->student)
            ->postJson($this->messagesUrl($this->conversation()), ['content' => '質問です'])
            ->assertStatus(502)
            ->assertJsonPath('upstream_status', null)
            ->assertJsonPath('assistant_message.status', 'error');
    }

    public function test_empty_answer_is_treated_as_failure(): void
    {
        Http::fake([self::GEMINI_URL => Http::response([
            'candidates' => [['content' => ['role' => 'model', 'parts' => []], 'finishReason' => 'SAFETY']],
        ])]);

        $this->actingAs($this->student)
            ->postJson($this->messagesUrl($this->conversation()), ['content' => '質問です'])
            ->assertStatus(502)
            ->assertJsonPath('assistant_message.status', 'error');
    }

    public function test_rejects_empty_and_too_long_content(): void
    {
        $conversation = $this->conversation();
        $this->actingAs($this->student);

        $this->postJson($this->messagesUrl($conversation), ['content' => ''])->assertUnprocessable();
        $this->postJson($this->messagesUrl($conversation), ['content' => str_repeat('あ', 2001)])->assertUnprocessable();

        Http::assertNothingSent();
        $this->assertDatabaseCount('ai_chat_messages', 0);
    }

    public function test_daily_limit_counts_failed_messages_and_resets_next_day(): void
    {
        config(['ai-chat.daily_limit' => 2]);
        Http::fake([self::GEMINI_URL => Http::sequence()
            ->push(['error' => ['message' => 'high demand']], 503)
            ->push($this->geminiResponse('回答です。'))
            ->push($this->geminiResponse('翌日の回答です。'))]);
        $conversation = $this->conversation();
        $this->actingAs($this->student);

        $this->postJson($this->messagesUrl($conversation), ['content' => '1 回目'])->assertStatus(502);
        $this->postJson($this->messagesUrl($conversation), ['content' => '2 回目'])->assertOk();
        $this->postJson($this->messagesUrl($conversation), ['content' => '3 回目'])->assertStatus(429);

        $this->assertDatabaseMissing('ai_chat_messages', ['content' => '3 回目']);

        $this->travelTo(now()->addDay()->startOfDay());
        $this->postJson($this->messagesUrl($conversation), ['content' => '翌日'])->assertOk();
    }

    public function test_daily_limit_is_not_reset_by_deleting_conversation(): void
    {
        config(['ai-chat.daily_limit' => 1]);
        Http::fake([self::GEMINI_URL => Http::response($this->geminiResponse('回答です。'))]);
        $this->actingAs($this->student);

        $first = $this->conversation();
        $this->postJson($this->messagesUrl($first), ['content' => '質問です'])->assertOk();
        $this->delete(route('ai-chat.conversations.destroy', $first));

        $this->postJson($this->messagesUrl($this->conversation()), ['content' => '質問です'])->assertStatus(429);
    }

    public function test_shows_unavailable_message_without_calling_gemini_when_api_key_is_missing(): void
    {
        config(['ai-chat.gemini.api_key' => null, 'ai-chat.daily_limit' => 1]);
        Http::fake();
        $conversation = $this->conversation();
        $this->actingAs($this->student);

        $this->postJson($this->messagesUrl($conversation), ['content' => '質問です'])
            ->assertOk()
            ->assertJsonPath('user_message.content', '質問です')
            ->assertJsonPath('assistant_message.status', 'error')
            ->assertJsonPath('assistant_message.content', 'AI 相談は現在ご利用いただけません。');

        // 日次上限(1 回)に数えられていないので、2 回目も同じ案内になる(429 にならない)
        $this->postJson($this->messagesUrl($conversation), ['content' => 'もう一度'])
            ->assertOk()
            ->assertJsonPath('assistant_message.content', 'AI 相談は現在ご利用いただけません。');

        Http::assertNothingSent();
    }

    public function test_section_context_is_sent_to_gemini(): void
    {
        Http::fake([self::GEMINI_URL => Http::response($this->geminiResponse('回答です。'))]);
        $conversation = AiChatConversation::factory()->for($this->student)->forSection($this->section)->create();

        $this->actingAs($this->student)
            ->postJson($this->messagesUrl($conversation), ['content' => 'この教材の要点は？'])
            ->assertOk();

        Http::assertSent(function (Request $request) {
            $system = $request['systemInstruction']['parts'][0]['text'];

            return str_contains($system, '基本情報技術者試験')
                && str_contains($system, $this->section->title);
        });
    }

    public function test_long_section_body_is_truncated_with_heading_list(): void
    {
        config(['ai-chat.section_body_limit' => 20]);
        $this->section->update(['body' => "## はじめに\n".str_repeat('あ', 100)."\n## 後半の見出し\n後半の本文"]);
        Http::fake([self::GEMINI_URL => Http::response($this->geminiResponse('回答です。'))]);
        $conversation = AiChatConversation::factory()->for($this->student)->forSection($this->section)->create();

        $this->actingAs($this->student)
            ->postJson($this->messagesUrl($conversation), ['content' => '後半の内容は？'])
            ->assertOk();

        Http::assertSent(function (Request $request) {
            $system = $request['systemInstruction']['parts'][0]['text'];

            return str_contains($system, '## 後半の見出し')
                && ! str_contains($system, '後半の本文');
        });
    }

    public function test_history_excludes_failed_exchange(): void
    {
        Http::fake([self::GEMINI_URL => Http::response($this->geminiResponse('回答です。'))]);
        $conversation = $this->conversation();
        AiChatMessage::factory()->for($conversation, 'conversation')->create(['content' => '二分探索木とは？']);
        AiChatMessage::factory()->for($conversation, 'conversation')->assistant()->create(['content' => '左が小さい二分木です。']);
        AiChatMessage::factory()->for($conversation, 'conversation')->create(['content' => '平均の比較回数は？']);
        AiChatMessage::factory()->for($conversation, 'conversation')->error()->create();

        $this->actingAs($this->student)
            ->postJson($this->messagesUrl($conversation), ['content' => '平均の比較回数は？'])
            ->assertOk();

        Http::assertSent(fn (Request $request) => array_map(
            fn (array $content) => [$content['role'], $content['parts'][0]['text']],
            $request['contents'],
        ) === [
            ['user', '二分探索木とは？'],
            ['model', '左が小さい二分木です。'],
            ['user', '平均の比較回数は？'],
        ]);
    }

    public function test_history_is_limited_to_recent_messages(): void
    {
        config(['ai-chat.history_limit' => 2]);
        Http::fake([self::GEMINI_URL => Http::response($this->geminiResponse('回答です。'))]);
        $conversation = $this->conversation();
        foreach (['1', '2', '3'] as $no) {
            AiChatMessage::factory()->for($conversation, 'conversation')->create(['content' => "質問{$no}"]);
            AiChatMessage::factory()->for($conversation, 'conversation')->assistant()->create(['content' => "回答{$no}"]);
        }

        $this->actingAs($this->student)
            ->postJson($this->messagesUrl($conversation), ['content' => '質問4'])
            ->assertOk();

        Http::assertSent(fn (Request $request) => array_column(array_column($request['contents'], 'parts'), 0) === [
            ['text' => '質問3'],
            ['text' => '回答3'],
            ['text' => '質問4'],
        ]);
    }

    public function test_title_is_regenerated_after_first_answer(): void
    {
        config(['ai-chat.auto_title' => true]);
        Http::fake([self::GEMINI_URL => Http::sequence()
            ->push($this->geminiResponse('回答です。'))
            ->push($this->geminiResponse('二分探索木の基本'))]);
        $conversation = $this->conversation();

        $this->actingAs($this->student)
            ->postJson($this->messagesUrl($conversation), ['content' => '二分探索木とは？'])
            ->assertOk()
            ->assertJsonPath('conversation.title', '二分探索木の基本');

        Http::assertSentCount(2);
    }

    public function test_title_is_not_regenerated_when_edited_by_user(): void
    {
        config(['ai-chat.auto_title' => true]);
        Http::fake([self::GEMINI_URL => Http::response($this->geminiResponse('回答です。'))]);
        $conversation = AiChatConversation::factory()->for($this->student)->titleEditedByUser()->create(['title' => '自分で付けたタイトル']);

        $this->actingAs($this->student)
            ->postJson($this->messagesUrl($conversation), ['content' => '二分探索木とは？'])
            ->assertOk()
            ->assertJsonPath('conversation.title', '自分で付けたタイトル');

        Http::assertSentCount(1);
    }

    public function test_provisional_title_is_set_from_first_question_when_auto_title_is_off(): void
    {
        Http::fake([self::GEMINI_URL => Http::response($this->geminiResponse('回答です。'))]);
        $conversation = $this->conversation();

        $this->actingAs($this->student)
            ->postJson($this->messagesUrl($conversation), ['content' => '二分探索木とは？'])
            ->assertOk()
            ->assertJsonPath('conversation.title', '二分探索木とは？');

        Http::assertSentCount(1);
    }

    private function conversation(): AiChatConversation
    {
        return AiChatConversation::factory()->for($this->student)->create(['title' => '新しい相談']);
    }

    private function messagesUrl(AiChatConversation $conversation): string
    {
        return route('ai-chat.conversations.messages.store', $conversation);
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
