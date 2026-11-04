<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * 開発用 AI 相談シーダー。
 *
 * 固定の受講生(student@certify-lms.test)に、次の会話を投入する。
 * - 教材(Section)から始めた会話: 教材の文脈バッジ、同じ教材から開いたときの会話の再開を確認する
 * - AI 応答がエラーの会話: エラー表示と、同じ内容を送り直しての再質問を確認する
 * - 教材によらない会話(全般相談)2 件: 過去の相談の再開・履歴表示を確認する
 *
 * 履歴の「今日 / 過去 7 日 / 過去 30 日」のグループ表示を確認できるよう、会話の日時をずらしている。
 *
 * 依存順序: `UserSeeder` → `EnrollmentSeeder` → `ContentSeeder` → 本 Seeder。
 */
final class AiChatSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()->where('email', 'student@certify-lms.test')->first();
        if ($student === null) {
            $this->command?->warn('AiChatSeeder: 固定の受講生が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        $enrollment = $this->learningEnrollment($student);
        if ($enrollment === null) {
            $this->command?->warn('AiChatSeeder: 受講中の受講登録が存在しません。先に EnrollmentSeeder を実行してください。');

            return;
        }

        $section = $this->publishedSection($enrollment);
        if ($section !== null) {
            $this->seedSectionConversation($student, $enrollment, $section);
        }

        $this->seedErrorConversation($student, $enrollment);
        $this->seedGeneralConversations($student, $enrollment);
    }

    private function learningEnrollment(User $student): ?Enrollment
    {
        $default = $student->defaultEnrollment;
        if ($default !== null && $default->status === EnrollmentStatus::Learning) {
            return $default;
        }

        return Enrollment::query()
            ->where('user_id', $student->id)
            ->where('status', EnrollmentStatus::Learning->value)
            ->orderBy('created_at')
            ->first();
    }

    /**
     * 受講生が閲覧できる(Part / Chapter / Section がすべて公開中の)最初の Section。
     */
    private function publishedSection(Enrollment $enrollment): ?Section
    {
        $published = ContentStatus::Published->value;

        return Section::query()
            ->where('status', $published)
            ->whereHas('chapter', fn ($chapter) => $chapter
                ->where('status', $published)
                ->whereHas('part', fn ($part) => $part
                    ->where('status', $published)
                    ->where('certification_id', $enrollment->certification_id)))
            ->orderBy('order')
            ->first();
    }

    private function seedSectionConversation(User $student, Enrollment $enrollment, Section $section): void
    {
        $conversation = $this->conversation($student, $enrollment, $section, $section->title.'の要点整理');
        $base = Carbon::now()->subHours(2);

        $this->user($conversation, 'このセクションの要点を 3 行でまとめて', $base);
        $this->assistant($conversation, "このセクションの要点は次の 3 つです。\n\n1. 用語の定義をまず押さえる\n2. 具体例と合わせて仕組みを理解する\n3. 試験では定義と具体例の対応がよく問われる", $base->copy()->addSeconds(12));
        $this->user($conversation, '試験でよく出るパターンを教えて', $base->copy()->addMinutes(3));
        $this->assistant($conversation, "よく出るのは次のパターンです。\n\n- 定義に当てはまる例を選ぶ問題\n- 似た用語との違いを問う問題\n\nまずは教材の例題を解き、選択肢ごとに正誤の理由を説明できるようにしておきましょう。", $base->copy()->addMinutes(3)->addSeconds(15));
    }

    private function seedErrorConversation(User $student, Enrollment $enrollment): void
    {
        $conversation = $this->conversation($student, $enrollment, null, '二分探索木の平均比較回数');
        $base = Carbon::yesterday()->setTime(21, 30);

        $this->user($conversation, '二分探索木とは何ですか？', $base);
        $this->assistant($conversation, '二分探索木は、各ノードについて「左の子孫は小さく、右の子孫は大きい」という規則で並べた二分木です。この規則のおかげで、探索のたびに候補を半分ほどに絞り込めます。', $base->copy()->addSeconds(10));
        // 最後のやり取りを失敗にしておき、同じ内容を送り直して再質問できることを確認できるようにする
        $this->user($conversation, '二分探索木の平均比較回数が O(log n) になる理由を教えて', $base->copy()->addMinutes(2));
        $this->failedAssistant($conversation, $base->copy()->addMinutes(2)->addSeconds(30));
    }

    private function seedGeneralConversations(User $student, Enrollment $enrollment): void
    {
        $recent = $this->conversation($student, $enrollment, null, '苦手分野の克服方法');
        $base = Carbon::now()->subDays(3)->setTime(20, 0);
        $this->user($recent, '苦手分野の克服アプローチを教えて', $base);
        $this->assistant($recent, "苦手分野は次の順で進めると効率的です。\n\n1. 演習で間違えた問題の分野を洗い出す\n2. 教材の該当セクションを読み直す\n3. 同じ分野の問題を続けて解き、正答率の変化を確認する", $base->copy()->addSeconds(14));

        $older = $this->conversation($student, $enrollment, null, '学習計画の立て方');
        $base = Carbon::now()->subDays(12)->setTime(22, 0);
        $this->user($older, '試験まで 2 か月です。学習計画の立て方を教えて', $base);
        $this->assistant($older, "2 か月の場合、次の配分がおすすめです。\n\n- 1 か月目: 教材を一通り読み、各セクションの演習問題を解く\n- 2 か月目前半: 苦手分野の復習と模擬試験\n- 2 か月目後半: 模擬試験の見直しと総復習", $base->copy()->addSeconds(11));
    }

    private function conversation(User $student, Enrollment $enrollment, ?Section $section, string $title): AiChatConversation
    {
        return AiChatConversation::create([
            'user_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'section_id' => $section?->id,
            'title' => $title,
        ]);
    }

    private function user(AiChatConversation $conversation, string $content, Carbon $at): void
    {
        $this->message($conversation, [
            'role' => AiChatMessageRole::User,
            'status' => AiChatMessageStatus::Completed,
            'content' => $content,
        ], $at);
    }

    private function assistant(AiChatConversation $conversation, string $content, Carbon $at): void
    {
        $this->message($conversation, [
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Completed,
            'content' => $content,
            'model' => config('ai-chat.gemini.model'),
            'input_tokens' => 800,
            'output_tokens' => mb_strlen($content),
            'response_time_ms' => 9500,
        ], $at);
    }

    private function failedAssistant(AiChatConversation $conversation, Carbon $at): void
    {
        $this->message($conversation, [
            'role' => AiChatMessageRole::Assistant,
            'status' => AiChatMessageStatus::Error,
            'content' => '',
            'model' => config('ai-chat.gemini.model'),
            'error_detail' => 'HTTP 503: This model is currently experiencing high demand. Spikes in demand are usually temporary. Please try again later.',
        ], $at);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function message(AiChatConversation $conversation, array $attributes, Carbon $at): void
    {
        $message = AiChatMessage::create(['ai_chat_conversation_id' => $conversation->id, ...$attributes]);
        $message->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

        $conversation->forceFill([
            'last_message_at' => $at,
            'created_at' => $conversation->wasRecentlyCreated && $conversation->messages()->count() === 1 ? $at : $conversation->created_at,
            'updated_at' => $at,
        ])->save();
    }
}
