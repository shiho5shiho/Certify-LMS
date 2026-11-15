<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 開発用 質問掲示板シーダー。
 *
 * - 公開中の資格ごとに、未解決・解決済を混在させたスレッドを投入する
 * - 回答数(0 件 / 数件)と作成日時をばらつかせる(絞り込み・並び順・ページネーション・削除を確認)
 * - 固定の受講生が投稿したスレッドを含める(「自分の質問」や解決マークの動線を確認)
 */
class QaBoardSeeder extends Seeder
{
    private const THREADS_PER_CERTIFICATION = 6;

    public function run(): void
    {
        $student = User::query()->where('email', 'student@certify-lms.test')->first();
        if ($student === null) {
            $this->command?->warn('QaBoardSeeder: 固定の受講生が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        $otherStudents = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereKeyNot($student->id)
            ->get();
        $posters = $otherStudents->isEmpty() ? collect([$student]) : $otherStudents;

        $certifications = Certification::query()
            ->where('status', CertificationStatus::Published->value)
            ->with('coaches')
            ->get();

        foreach ($certifications as $certification) {
            for ($i = 1; $i <= self::THREADS_PER_CERTIFICATION; $i++) {
                // 各資格の先頭 2 件は固定の受講生の投稿にする
                $poster = $i <= 2 ? $student : $posters->random();
                $postedAt = now()->subDays(random_int(1, 60))->subMinutes(random_int(0, 1440));

                $factory = QaThread::factory()->for($certification)->for($poster, 'user');
                $thread = ($i % 3 === 0 ? $factory->resolved() : $factory)->create([
                    'created_at' => $postedAt,
                    'updated_at' => $postedAt,
                ]);

                // 回答数を 0 / 1 / 2 / 3 件でばらつかせる(0 件のスレッドはコーチダッシュボードの未回答に出る)
                $repliers = $certification->coaches->concat($posters)->values();
                $replyCount = $i % 4;
                for ($j = 1; $j <= $replyCount; $j++) {
                    $repliedAt = $postedAt->copy()->addHours($j);
                    QaReply::factory()->for($thread, 'thread')->for($repliers->random(), 'user')->create([
                        'created_at' => $repliedAt,
                        'updated_at' => $repliedAt,
                    ]);
                }
            }
        }
    }
}
