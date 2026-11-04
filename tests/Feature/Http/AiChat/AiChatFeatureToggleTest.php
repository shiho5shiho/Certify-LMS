<?php

declare(strict_types=1);

namespace Tests\Feature\Http\AiChat;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 機能 OFF(AI_CHAT_ENABLED=false)のとき、AI 相談のルートが登録されないことのテスト。
 *
 * ルートはアプリ起動時に登録されるため、環境変数を切り替えてからアプリを作り直して確認する。
 * DB を使わないので RefreshDatabase は使わない(アプリを作り直すとトランザクションが引き継がれないため)。
 */
class AiChatFeatureToggleTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->setAiChatEnabled('true');

        parent::tearDown();
    }

    public function test_routes_are_registered_when_enabled(): void
    {
        $this->assertTrue(Route::has('ai-chat.index'));
        $this->assertTrue(Route::has('ai-chat.conversations.messages.store'));
    }

    public function test_routes_are_not_registered_when_disabled(): void
    {
        $this->setAiChatEnabled('false');
        $this->refreshApplication();

        $this->assertFalse(Route::has('ai-chat.index'));
        $this->assertFalse(Route::has('ai-chat.conversations.messages.store'));
        $this->get('/ai-chat')->assertNotFound();
    }

    private function setAiChatEnabled(string $value): void
    {
        putenv("AI_CHAT_ENABLED={$value}");
        $_ENV['AI_CHAT_ENABLED'] = $value;
        $_SERVER['AI_CHAT_ENABLED'] = $value;
    }
}
