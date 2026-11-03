<?php

declare(strict_types=1);

/**
 * AI 相談(Gemini チャットボット)の設定。
 *
 * 機能の ON/OFF・日次上限・履歴件数などは .env で変更できる。
 * システムプロンプトは管理画面を持たず、本ファイルで管理する(要件: 環境設定で完結)。
 */
return [
    // 機能全体の ON/OFF。false にするとルート・サイドバー・ウィジェットがすべて利用不可になる
    'enabled' => (bool) env('AI_CHAT_ENABLED', true),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 60),
    ],

    // 受講生 1 人あたりの 1 日の送信上限(日本時間 0:00 区切り、AI 応答の失敗分も含む)
    'daily_limit' => (int) env('AI_CHAT_DAILY_LIMIT', 30),

    // AI に引き継ぐ直近の履歴件数(エラーになったやり取りは除外)
    'history_limit' => (int) env('AI_CHAT_HISTORY_LIMIT', 10),

    // AI に渡す Section 本文の上限文字数(超える場合は先頭 + 見出し一覧を渡す)
    'section_body_limit' => (int) env('AI_CHAT_SECTION_BODY_LIMIT', 3000),

    // 会話タイトルの自動付け直し(最初の AI 応答の成功時に 1 回だけ)の ON/OFF
    'auto_title' => (bool) env('AI_CHAT_AUTO_TITLE', true),

    'system_prompt' => <<<'PROMPT'
        あなたは資格学習サービス「Certify LMS」の学習補助 AI です。
        受講生の学習相談に、日本語でわかりやすく簡潔に答えてください。
        資格や教材の情報が渡された場合は、その内容を踏まえて回答してください。
        教材本文が途中までしか渡されていない場合は、渡された範囲に含まれない内容を推測で断定せず、その旨を伝えてください。
        学習と関係のない相談には、学習に関する相談を受け付けている旨を丁寧に伝えてください。
        PROMPT,
];
