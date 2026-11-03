<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Section;

/**
 * Gemini に渡すシステムへの指示(資格・教材の文脈)と、会話の履歴を組み立てる。
 *
 * - 資格: 教材から開いた会話は教材の資格、それ以外は会話の受講登録 → 既定の受講登録(learning / passed のみ)
 * - 教材: タイトルと本文。本文が上限文字数を超える場合は先頭部分と本文全体の見出し一覧を渡す(PM 確認 Q3)
 * - 履歴: 直近 N 件(config: ai-chat.history_limit)。エラーになったやり取りは、その直前の質問ごと除く(PM 確認 Q2)
 */
final class AiChatPromptBuilder
{
    /**
     * @return array{system: string, contents: list<array{role: 'user'|'model', text: string}>}
     */
    public function build(AiChatConversation $conversation, AiChatMessage $question): array
    {
        $conversation->loadMissing([
            'section.chapter.part.certification',
            'enrollment.certification',
            'user.defaultEnrollment.certification',
        ]);

        return [
            'system' => $this->systemInstruction($conversation),
            'contents' => [
                ...$this->history($conversation, $question),
                ['role' => 'user', 'text' => $question->content],
            ],
        ];
    }

    private function systemInstruction(AiChatConversation $conversation): string
    {
        $blocks = [trim((string) config('ai-chat.system_prompt'))];

        $certificationName = $this->certificationName($conversation);
        if ($certificationName !== null) {
            $blocks[] = "## 受講生が学習中の資格\n{$certificationName}";
        }

        if ($conversation->section !== null) {
            $blocks[] = "## 受講生が閲覧中の教材\nタイトル: {$conversation->section->title}\n\n".$this->sectionBody($conversation->section);
        }

        return implode("\n\n", $blocks);
    }

    private function certificationName(AiChatConversation $conversation): ?string
    {
        $sectionCertification = $conversation->section?->chapter?->part?->certification;
        if ($sectionCertification !== null) {
            return $sectionCertification->name;
        }

        // 画面のコンテキストバッジ(_partials/context-badges)と同じ解決順・同じ状態条件にそろえる
        $enrollment = $conversation->enrollment ?? $conversation->user->defaultEnrollment;

        if ($enrollment === null || ! in_array($enrollment->status, [EnrollmentStatus::Learning, EnrollmentStatus::Passed], true)) {
            return null;
        }

        return $enrollment->certification?->name;
    }

    private function sectionBody(Section $section): string
    {
        $body = (string) $section->body;
        $limit = (int) config('ai-chat.section_body_limit');

        if (mb_strlen($body) <= $limit) {
            return "本文:\n{$body}";
        }

        // 後半に回答に必要な情報がある場合に備え、本文全体の見出し一覧を添える(PM 確認 Q3)
        $headings = collect(preg_split('/\R/u', $body))
            ->filter(fn (string $line) => preg_match('/^#{1,6}\s+\S/u', $line) === 1)
            ->implode("\n");

        return "本文(先頭 {$limit} 文字のみ。以降は省略):\n".mb_substr($body, 0, $limit)
            ."\n\n本文全体の見出し一覧:\n".$headings;
    }

    /**
     * @return list<array{role: 'user'|'model', text: string}>
     */
    private function history(AiChatConversation $conversation, AiChatMessage $question): array
    {
        $messages = $conversation->messages()
            ->whereKeyNot($question->getKey())
            ->whereIn('status', [AiChatMessageStatus::Completed->value, AiChatMessageStatus::Error->value])
            ->get();

        $history = [];
        foreach ($messages as $message) {
            if ($message->status === AiChatMessageStatus::Error) {
                // 失敗したやり取りは、直前の受講生の質問ごと除く(送り直しで同じ質問が 2 回入らないように)
                if ($history !== [] && end($history)['role'] === 'user') {
                    array_pop($history);
                }

                continue;
            }

            $history[] = [
                'role' => $message->role === AiChatMessageRole::User ? 'user' : 'model',
                'text' => $message->content,
            ];
        }

        $recent = array_slice($history, -(int) config('ai-chat.history_limit'));

        // 件数で切ったときに AI の応答から始まる場合は、その 1 件を落として受講生の質問から始める
        if ($recent !== [] && $recent[0]['role'] === 'model') {
            array_shift($recent);
        }

        return array_values($recent);
    }
}
