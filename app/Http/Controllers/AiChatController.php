<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\AiChat\AiChatUnavailableException;
use App\Http\Requests\AiChat\StoreConversationRequest;
use App\Http\Requests\AiChat\StoreMessageRequest;
use App\Http\Requests\AiChat\UpdateConversationRequest;
use App\Http\Resources\AiChatConversationResource;
use App\Http\Resources\AiChatMessageResource;
use App\Models\AiChatConversation;
use App\Models\Section;
use App\UseCases\AiChat\DestroyAction;
use App\UseCases\AiChat\SendMessageAction;
use App\UseCases\AiChat\StoreAction;
use App\UseCases\AiChat\UpdateAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AI 相談(受講生専用)Controller。
 *
 * 画面(フル画面)と JSON(ウィジェット / フル画面の非同期送信)の両方を返す。
 * JSON の形は提供済みの JS(resources/js/ai-chat/)に合わせる。
 */
class AiChatController extends Controller
{
    /**
     * 会話が 0 件なら空状態、1 件以上なら最新の会話のフル画面へ移る。
     */
    public function index(Request $request): View|RedirectResponse
    {
        $latest = $request->user()->aiChatConversations()
            ->orderByDesc('last_message_at')
            ->first();

        if ($latest !== null) {
            // 削除後などのトーストを、移動先の画面まで持ち越す
            $request->session()->reflash();

            return redirect()->route('ai-chat.conversations.show', $latest);
        }

        $this->noticeIfUnavailable();

        return view('ai-chat.empty-state');
    }

    public function store(StoreConversationRequest $request, StoreAction $store, SendMessageAction $send): JsonResponse|RedirectResponse
    {
        $student = $request->user();

        $section = null;
        if ($request->filled('section_id')) {
            $section = Section::findOrFail($request->validated('section_id'));
            // 受講登録していない資格の教材を文脈として渡せないようにする
            $this->authorize('learning.section.view', $section);
        }

        $message = (string) $request->validated('message', '');

        // 最初の質問がある場合は、送れない状態なら会話も作らない
        if ($message !== '') {
            $send->ensureSendable($student);
        }

        ['conversation' => $conversation, 'created' => $created] = $store($student, $section);

        if ($request->expectsJson()) {
            return response()->json([
                'conversation' => new AiChatConversationResource($conversation),
            ], $created ? 201 : 200);
        }

        // 新しい相談モーダル: AI の応答が失敗しても、エラーは会話の画面に表示されるのでそのまま移る
        if ($message !== '') {
            $send($conversation, $message);
        }

        return redirect()->route('ai-chat.conversations.show', $conversation);
    }

    public function show(Request $request, AiChatConversation $conversation): View|JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation->load(['messages', 'enrollment.certification', 'section']);

        if ($request->expectsJson()) {
            return response()->json([
                'messages' => AiChatMessageResource::collection($conversation->messages),
            ]);
        }

        $this->noticeIfUnavailable();

        return view('ai-chat.show', [
            'conversation' => $conversation,
        ]);
    }

    public function update(UpdateConversationRequest $request, AiChatConversation $conversation, UpdateAction $action): RedirectResponse
    {
        $action($conversation, $request->validated());

        return redirect()
            ->route('ai-chat.conversations.show', $conversation)
            ->with('success', 'タイトルを更新しました。');
    }

    public function destroy(AiChatConversation $conversation, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $conversation);

        $action($conversation);

        return redirect()
            ->route('ai-chat.index')
            ->with('success', '相談を削除しました。');
    }

    public function storeMessage(StoreMessageRequest $request, AiChatConversation $conversation, SendMessageAction $action): JsonResponse
    {
        $result = $action($conversation, $request->validated('content'));

        $payload = [
            'user_message' => new AiChatMessageResource($result['user_message']),
            'assistant_message' => new AiChatMessageResource($result['assistant_message']),
            'conversation' => new AiChatConversationResource($conversation->refresh()),
        ];

        if ($result['failure'] !== null) {
            return response()->json([
                'message' => 'AI の応答を取得できませんでした。',
                'upstream_status' => $result['failure']->upstreamStatus,
                ...$payload,
            ], 502);
        }

        return response()->json($payload);
    }

    /**
     * API キー未設定の環境では、画面を開いたときにトーストで案内する(PM 確認 Q5)。
     * リダイレクトせずに画面を返すため、今回の表示だけに出す session()->now() を使う。
     */
    private function noticeIfUnavailable(): void
    {
        if (blank(config('ai-chat.gemini.api_key'))) {
            session()->now('warning', AiChatUnavailableException::MESSAGE);
        }
    }
}
