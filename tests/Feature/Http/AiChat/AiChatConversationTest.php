<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

/**
 * AI 相談の会話(一覧・作成・表示・タイトル編集・削除)と、アクセス制御のテスト。
 * Gemini との通信は Http::fake() で差し替える。
 */
class AiChatConversationTest extends TestCase
{
    use ContentTestHelpers;
    use RefreshDatabase;

    private User $student;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['ai-chat.auto_title' => false]);

        $this->student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        Enrollment::factory()->for($this->student, 'user')->for($certification)->learning()->create();
        [, , $this->section] = $this->makePartChain($certification);
    }

    public function test_index_shows_empty_state_when_student_has_no_conversation(): void
    {
        $this->actingAs($this->student)
            ->get(route('ai-chat.index'))
            ->assertOk()
            ->assertViewIs('ai-chat.empty-state');
    }

    public function test_index_redirects_to_latest_conversation(): void
    {
        AiChatConversation::factory()->for($this->student)->create(['last_message_at' => now()->subDay()]);
        $latest = AiChatConversation::factory()->for($this->student)->create(['last_message_at' => now()]);

        $this->actingAs($this->student)
            ->get(route('ai-chat.index'))
            ->assertRedirect(route('ai-chat.conversations.show', $latest));
    }

    public function test_widget_creates_conversation_with_section_context(): void
    {
        $this->actingAs($this->student)
            ->postJson(route('ai-chat.conversations.store'), [
                'source' => 'widget',
                'section_id' => $this->section->id,
            ])
            ->assertCreated()
            ->assertJsonStructure(['conversation' => ['id', 'title']]);

        $this->assertDatabaseHas('ai_chat_conversations', [
            'user_id' => $this->student->id,
            'section_id' => $this->section->id,
            'title' => '新しい相談',
        ]);
    }

    public function test_widget_resumes_existing_conversation_for_same_section(): void
    {
        $existing = AiChatConversation::factory()
            ->for($this->student)
            ->forSection($this->section)
            ->create();

        $this->actingAs($this->student)
            ->postJson(route('ai-chat.conversations.store'), [
                'source' => 'widget',
                'section_id' => $this->section->id,
            ])
            ->assertOk()
            ->assertJsonPath('conversation.id', $existing->id);

        $this->assertDatabaseCount('ai_chat_conversations', 1);
    }

    public function test_general_conversation_is_always_created_as_new(): void
    {
        $this->actingAs($this->student);

        $this->postJson(route('ai-chat.conversations.store'), ['source' => 'widget'])->assertCreated();
        $this->postJson(route('ai-chat.conversations.store'), ['source' => 'widget'])->assertCreated();

        $this->assertDatabaseCount('ai_chat_conversations', 2);
    }

    public function test_cannot_use_section_of_certification_not_enrolled(): void
    {
        [, , $otherSection] = $this->makePartChain(Certification::factory()->published()->create());

        $this->actingAs($this->student)
            ->postJson(route('ai-chat.conversations.store'), [
                'source' => 'widget',
                'section_id' => $otherSection->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('ai_chat_conversations', 0);
    }

    public function test_new_conversation_modal_sends_first_message_and_redirects(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->geminiResponse('回答です。'))]);

        $response = $this->actingAs($this->student)
            ->post(route('ai-chat.conversations.store'), [
                'source' => 'full-screen',
                'message' => '二分探索木の平均比較回数は？',
            ]);

        $conversation = AiChatConversation::firstOrFail();
        $response->assertRedirect(route('ai-chat.conversations.show', $conversation));
        $this->assertSame(2, $conversation->messages()->count());
    }

    public function test_new_conversation_modal_does_not_create_conversation_when_api_key_is_missing(): void
    {
        config(['ai-chat.gemini.api_key' => null]);

        $this->actingAs($this->student)
            ->from(route('ai-chat.index'))
            ->post(route('ai-chat.conversations.store'), [
                'source' => 'full-screen',
                'message' => '質問です',
            ])
            ->assertRedirect(route('ai-chat.index'))
            ->assertSessionHas('error', 'AI 相談は現在ご利用いただけません。');

        $this->assertDatabaseCount('ai_chat_conversations', 0);
        Http::assertNothingSent();
    }

    public function test_show_displays_conversation_to_owner(): void
    {
        $conversation = AiChatConversation::factory()->for($this->student)->create(['title' => '計算量の相談']);

        $this->actingAs($this->student)
            ->get(route('ai-chat.conversations.show', $conversation))
            ->assertOk()
            ->assertViewIs('ai-chat.show')
            ->assertSee('計算量の相談');
    }

    public function test_show_notices_unavailable_when_api_key_is_missing(): void
    {
        config(['ai-chat.gemini.api_key' => null]);
        $conversation = AiChatConversation::factory()->for($this->student)->create();

        $this->actingAs($this->student)
            ->get(route('ai-chat.conversations.show', $conversation))
            ->assertOk()
            ->assertSee('AI 相談は現在ご利用いただけません。');
    }

    public function test_show_json_returns_messages_with_error_text_for_failed_answer(): void
    {
        $conversation = AiChatConversation::factory()->for($this->student)->create();
        AiChatMessage::factory()->for($conversation, 'conversation')->create(['content' => '質問です']);
        AiChatMessage::factory()->for($conversation, 'conversation')->error()->create();

        $this->actingAs($this->student)
            ->getJson(route('ai-chat.conversations.show', $conversation))
            ->assertOk()
            ->assertJsonPath('messages.0.role', 'user')
            ->assertJsonPath('messages.0.content', '質問です')
            ->assertJsonPath('messages.1.status', 'error')
            ->assertJsonPath('messages.1.content', 'Gemini API が一時的に応答していません。少し待って再試行してください。');
    }

    public function test_owner_can_rename_conversation(): void
    {
        $conversation = AiChatConversation::factory()->for($this->student)->create();

        $this->actingAs($this->student)
            ->patch(route('ai-chat.conversations.update', $conversation), ['title' => '計算量の整理'])
            ->assertRedirect(route('ai-chat.conversations.show', $conversation))
            ->assertSessionHas('success', 'タイトルを更新しました。');

        $conversation->refresh();
        $this->assertSame('計算量の整理', $conversation->title);
        $this->assertTrue($conversation->title_edited_by_user);
    }

    public function test_rename_rejects_title_longer_than_100_characters(): void
    {
        $conversation = AiChatConversation::factory()->for($this->student)->create();

        $this->actingAs($this->student)
            ->patch(route('ai-chat.conversations.update', $conversation), ['title' => str_repeat('あ', 101)])
            ->assertSessionHasErrors('title');
    }

    public function test_owner_can_delete_conversation_with_messages(): void
    {
        $conversation = AiChatConversation::factory()->for($this->student)->create();
        AiChatMessage::factory()->for($conversation, 'conversation')->create();

        $this->actingAs($this->student)
            ->delete(route('ai-chat.conversations.destroy', $conversation))
            ->assertRedirect(route('ai-chat.index'))
            ->assertSessionHas('success', '相談を削除しました。');

        $this->assertModelMissing($conversation);
        $this->assertDatabaseCount('ai_chat_messages', 0);
    }

    public function test_other_student_cannot_access_conversation(): void
    {
        $conversation = AiChatConversation::factory()->for($this->student)->create();
        $other = User::factory()->student()->inProgress()->create();

        $this->actingAs($other);
        $this->get(route('ai-chat.conversations.show', $conversation))->assertForbidden();
        $this->getJson(route('ai-chat.conversations.show', $conversation))->assertForbidden();
        $this->patch(route('ai-chat.conversations.update', $conversation), ['title' => '変更'])->assertForbidden();
        $this->delete(route('ai-chat.conversations.destroy', $conversation))->assertForbidden();
        $this->postJson(route('ai-chat.conversations.messages.store', $conversation), ['content' => '質問'])->assertForbidden();

        $this->assertModelExists($conversation);
    }

    /**
     * @return array<string, array{0: callable(): User}>
     */
    public static function nonEligibleUserProvider(): array
    {
        return [
            'コーチ' => [fn () => User::factory()->coach()->inProgress()->create()],
            '管理者' => [fn () => User::factory()->admin()->create()],
            '卒業した受講生' => [fn () => User::factory()->student()->graduated()->create()],
        ];
    }

    /**
     * @dataProvider nonEligibleUserProvider
     */
    public function test_only_in_progress_student_can_use_ai_chat(callable $makeUser): void
    {
        $this->actingAs($makeUser())
            ->get(route('ai-chat.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('ai-chat.index'))->assertRedirect(route('login'));
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
