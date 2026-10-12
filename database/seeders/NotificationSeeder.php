<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EnrollmentStatus;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\BusinessEventNotification;
use App\Notifications\Chat\ChatMessageReceivedNotification;
use App\Notifications\Meeting\MeetingCanceledNotification;
use App\Notifications\Meeting\MeetingReservedNotification;
use App\Notifications\QaBoard\QaReplyReceivedNotification;
use App\Notifications\QaBoard\QaThreadPostedNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 固定の受講生(student@)・コーチ(coach@)に、既読・未読と種別が混在した通知を投入する。
 *
 * - 一覧のページ送り(20 件 / ページ)を確認できるよう、各 24 件を投入する
 * - 通知の中身は各通知クラスの toArray() で生成し、本番の通知と文言を揃える
 * - 行クリックで実在の画面へ移動できるよう、ChatSeeder / MentoringSeeder のデモデータを参照する
 *   (質問掲示板とキャンセル済み面談は本 Seeder で 1 件ずつ作成する)
 *
 * ChatSeeder / MentoringSeeder の後に実行する前提。
 */
class NotificationSeeder extends Seeder
{
    private const COUNT_PER_USER = 24;

    public function run(): void
    {
        $student = User::query()->where('email', 'student@certify-lms.test')->first();
        $coach = User::query()->where('email', 'coach@certify-lms.test')->first();
        if ($student === null || $coach === null) {
            return;
        }

        $enrollment = Enrollment::query()
            ->where('user_id', $student->id)
            ->where('status', EnrollmentStatus::Learning->value)
            ->orderBy('created_at')
            ->first();
        if ($enrollment === null) {
            return;
        }

        $room = ChatRoom::query()->where('enrollment_id', $enrollment->id)->first();
        $coachMessage = $this->latestMessage($room, $coach);
        $studentMessage = $this->latestMessage($room, $student);
        $reserved = Meeting::query()
            ->where('student_id', $student->id)
            ->where('coach_id', $coach->id)
            ->where('status', 'reserved')
            ->first();
        if ($coachMessage === null || $studentMessage === null || $reserved === null) {
            return;
        }

        $thread = QaThread::factory()->create([
            'certification_id' => $enrollment->certification_id,
            'user_id' => $student->id,
            'title' => '2 分探索木の比較回数の数え方が分かりません',
            'body' => '章末の例題で、探索に必要な比較回数の数え方が解説と合いません。どこから数えればよいでしょうか。',
        ]);
        $reply = QaReply::factory()->create([
            'qa_thread_id' => $thread->id,
            'user_id' => $coach->id,
            'body' => '根から数え始めて、比較した節点の数がそのまま比較回数になります。例題の図で一緒に追ってみましょう。',
        ]);

        $canceled = Meeting::factory()
            ->canceled()
            ->forCoach($coach)
            ->forStudent($student)
            ->forEnrollment($enrollment)
            ->create([
                'scheduled_at' => now()->addDays(3)->setTime(10, 30),
                'canceled_by_user_id' => $student->id,
                'topic' => '過去問の進め方を相談したいです。',
            ]);

        $this->seedFor($student, [
            new ChatMessageReceivedNotification($coachMessage),
            new QaReplyReceivedNotification($reply),
        ]);

        $this->seedFor($coach, [
            new ChatMessageReceivedNotification($studentMessage),
            new QaThreadPostedNotification($thread),
            new MeetingReservedNotification($reserved),
            new MeetingCanceledNotification($canceled, $student),
        ]);
    }

    private function latestMessage(?ChatRoom $room, User $sender): ?ChatMessage
    {
        if ($room === null) {
            return null;
        }

        return ChatMessage::query()
            ->where('chat_room_id', $room->id)
            ->where('sender_user_id', $sender->id)
            ->latest()
            ->first();
    }

    /**
     * 渡した通知を順番に繰り返して COUNT_PER_USER 件投入する。
     * 5 時間ずつ過去にずらし、新しいものから 3 件に 1 件を未読にする。
     *
     * @param array<int, BusinessEventNotification> $notifications
     */
    private function seedFor(User $user, array $notifications): void
    {
        for ($i = 0; $i < self::COUNT_PER_USER; $i++) {
            $notification = $notifications[$i % count($notifications)];
            $createdAt = now()->subHours($i * 5);

            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => $notification::class,
                'data' => $notification->toArray($user),
                'read_at' => $i % 3 === 0 ? null : $createdAt->copy()->addHour(),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
