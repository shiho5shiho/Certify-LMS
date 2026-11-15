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
 * - 公開中の資格ごとに 8 件、未解決・解決済を混在させたスレッドを投入する
 * - 回答数(0 件 / 数件)と作成日時をばらつかせる(絞り込み・並び順・ページネーション・削除を確認)
 * - 固定の受講生が投稿したスレッドを含める(投稿者本人の編集・削除・解決マークの動線を確認)
 */
class QaBoardSeeder extends Seeder
{
    /**
     * スレッドの文面(タイトル / 本文)。資格を問わず使える学習相談の内容にしている。
     */
    private const THREADS = [
        ['title' => '過去問の解説で分からない箇所があります', 'body' => '過去問の解説を読んでも、途中の式変形がなぜそうなるのか理解できません。考え方のヒントをいただけないでしょうか。'],
        ['title' => '教材の用語の違いが分かりません', 'body' => '教材に似た用語が 2 つ出てきて、使い分けが分からなくなりました。それぞれどのような場面で使うのか教えてください。'],
        ['title' => '計算問題で毎回時間が足りなくなります', 'body' => '模試の計算問題で時間を使いすぎてしまい、最後まで解き終わりません。皆さんはどのように時間配分していますか。'],
        ['title' => '演習問題の正解の選択肢に納得できません', 'body' => '演習問題で自分の選んだ選択肢でも正しいように思えます。なぜ正解の選択肢だけが正しいのか、理由を知りたいです。'],
        ['title' => '暗記が必要な範囲の覚え方を知りたいです', 'body' => '覚える項目が多く、なかなか定着しません。効率よく覚えるための工夫があれば教えてください。'],
        ['title' => '章末問題の解き方について質問です', 'body' => '章末問題の 3 問目で、どこから手を付ければよいか分かりませんでした。解き始めの考え方を教えていただけますか。'],
        ['title' => '試験直前の学習の進め方について', 'body' => '試験まで残り 2 週間です。新しい範囲に進むべきか、これまでの復習に集中すべきか迷っています。'],
        ['title' => '図の読み取り方が分かりません', 'body' => '教材の図で、矢印が何を表しているのかが分からず、本文の説明とうまく結び付けられません。'],
    ];

    /**
     * 回答の文面。
     */
    private const REPLIES = [
        'まずは教材の該当 Section をもう一度読み、例題を自分の手で解き直してみてください。手を動かすと理解しやすくなります。',
        '私も同じところで迷いました。用語を表にまとめて見比べたら、違いが分かりやすくなりました。',
        '時間配分は、最初に全体を見て解けそうな問題から手を付けるのがおすすめです。',
        'ありがとうございます。教えていただいた方法で解き直したら理解できました。',
        '選択肢ごとに「正しい理由」と「誤りの理由」を書き出すと、判断の基準がはっきりします。',
        '直前期は新しい範囲よりも、間違えた問題の復習を優先するのがおすすめです。',
    ];

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
            foreach (self::THREADS as $index => $content) {
                $i = $index + 1;
                // 各資格の先頭 2 件は固定の受講生の投稿にする
                $poster = $i <= 2 ? $student : $posters->random();
                $postedAt = now()->subDays(random_int(1, 60))->subMinutes(random_int(0, 1440));

                $factory = QaThread::factory()->for($certification)->for($poster, 'user');
                $thread = ($i % 3 === 0 ? $factory->resolved() : $factory)->create([
                    'title' => $content['title'],
                    'body' => $content['body'],
                    'created_at' => $postedAt,
                    'updated_at' => $postedAt,
                ]);

                // 回答数を 0 / 1 / 2 / 3 件でばらつかせる(0 件のスレッドはコーチダッシュボードの未回答に出る)
                $repliers = $certification->coaches->concat($posters)->values();
                $replyCount = $i % 4;
                for ($j = 1; $j <= $replyCount; $j++) {
                    $repliedAt = $postedAt->copy()->addHours($j);
                    QaReply::factory()->for($thread, 'thread')->for($repliers->random(), 'user')->create([
                        'body' => self::REPLIES[array_rand(self::REPLIES)],
                        'created_at' => $repliedAt,
                        'updated_at' => $repliedAt,
                    ]);
                }
            }
        }
    }
}
