<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Enums\EnrollmentStatus;
use App\Models\AiChatConversation;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;

/**
 * AI 相談の会話の作成。
 *
 * 教材から開いた場合のみ、同じ受講生 × 同じ Section の最新の会話を続きとして使う(乱立防止)。
 * 全般相談・フル画面の「新しい相談」は毎回新規作成する。
 */
final class StoreAction
{
    public const DEFAULT_TITLE = '新しい相談';

    /**
     * @return array{conversation: AiChatConversation, created: bool}
     */
    public function __invoke(User $student, ?Section $section): array
    {
        if ($section !== null) {
            $existing = $student->aiChatConversations()
                ->where('section_id', $section->id)
                ->orderByDesc('last_message_at')
                ->first();

            if ($existing !== null) {
                return ['conversation' => $existing, 'created' => false];
            }
        }

        $conversation = AiChatConversation::create([
            'user_id' => $student->id,
            'enrollment_id' => $this->resolveEnrollment($student, $section)?->id,
            'section_id' => $section?->id,
            'title' => self::DEFAULT_TITLE,
            'last_message_at' => now(),
        ]);

        return ['conversation' => $conversation, 'created' => true];
    }

    /**
     * 資格の文脈として記録する受講登録。教材から開いた場合はその教材の資格、それ以外は既定の受講登録。
     * どちらも画面のコンテキストバッジと同じく learning / passed のみ対象。
     */
    private function resolveEnrollment(User $student, ?Section $section): ?Enrollment
    {
        $activeStatuses = [EnrollmentStatus::Learning, EnrollmentStatus::Passed];

        if ($section !== null) {
            $section->loadMissing('chapter.part');

            return $student->enrollments()
                ->where('certification_id', $section->chapter?->part?->certification_id)
                ->whereIn('status', array_map(fn (EnrollmentStatus $s) => $s->value, $activeStatuses))
                ->first();
        }

        $default = $student->defaultEnrollment;

        return $default !== null && in_array($default->status, $activeStatuses, true) ? $default : null;
    }
}
