<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Enums\CertificationStatus;
use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Certification;
use App\Models\Section;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Gemini に渡すシステムへの指示(資格・教材の文脈)と、会話の履歴を組み立てる。
 *
 * - 資格: 教材から開いた会話は教材の資格、それ以外は会話の受講登録 → 既定の受講登録(learning / passed のみ)。公開中でない資格は資格名も渡さない
 * - 教材: Part / Chapter / Section の番号(公開中の教材の中での並び順)と見出し(タイトル)のみ。本文は渡さない。受講生に公開されていない教材は渡さない
 * - 履歴: 直近 N 件(config: ai-chat.history_limit)。エラーになったやり取りは、その直前の質問ごと除く
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

        if ($conversation->section?->isVisibleToStudent()) {
            $blocks[] = "## 受講生が閲覧中の教材\n".$this->sectionHeadings($conversation->section);
        }

        return implode("\n\n", $blocks);
    }

    private function certificationName(AiChatConversation $conversation): ?string
    {
        // 教材から開いた会話は教材の資格だけを使う(公開中でなくても、ほかの受講登録の資格には切り替えない)
        $sectionCertification = $conversation->section?->chapter?->part?->certification;
        if ($sectionCertification !== null) {
            return $this->publishedCertificationName($sectionCertification);
        }

        // 画面のコンテキストバッジ(_partials/context-badges)と同じ解決順・同じ状態条件にそろえる
        $enrollment = $conversation->enrollment ?? $conversation->user->defaultEnrollment;

        if ($enrollment === null || ! in_array($enrollment->status, [EnrollmentStatus::Learning, EnrollmentStatus::Passed], true)) {
            return null;
        }

        return $this->publishedCertificationName($enrollment->certification);
    }

    private function publishedCertificationName(?Certification $certification): ?string
    {
        return $certification?->status === CertificationStatus::Published ? $certification->name : null;
    }

    private function sectionHeadings(Section $section): string
    {
        $chapter = $section->chapter;
        $part = $chapter->part;

        // 番号は教材画面と同じく、公開中の教材の中での並び順(1 始まり)
        $partNo = $this->position($part->certification->parts(), $part->id);
        $chapterNo = $this->position($part->chapters(), $chapter->id);
        $sectionNo = $this->position($chapter->sections(), $section->id);

        return "Part {$partNo}: {$part->title}\n"
            ."Chapter {$chapterNo}: {$chapter->title}\n"
            ."Section {$sectionNo}: {$section->title}";
    }

    private function position(HasMany $siblings, string $id): int
    {
        $ids = $siblings->where('status', ContentStatus::Published->value)->ordered()->pluck('id');

        return $ids->search($id) + 1;
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
