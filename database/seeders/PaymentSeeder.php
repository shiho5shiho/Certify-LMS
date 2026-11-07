<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\MeetingPack;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * 開発用 追加面談購入(決済)シーダー。
 *
 * 固定の受講生(student@certify-lms.test)とデモ受講生に、決済ステータスの異なる購入記録を投入する。
 * - 固定の受講生: 完了 / 処理中 / 失敗 を 1 件ずつ。面談回数履歴と、完了分のみ残数に反映されることを確認する
 * - デモ受講生 1: アーカイブ済みのパック(20 回パック(旧))の完了。販売終了後も購入履歴が残り、削除できないことを確認する
 * - デモ受講生 2: 完了 / 失敗。管理画面の購入履歴に複数の購入者が並ぶことを確認する
 *
 * 完了の購入には、Webhook と同じく purchased の面談回数トランザクションも作成する。
 * 残数 0 の確認用の受講生(student-noquota@certify-lms.test)には投入しない。
 * 決済画面の ID はダミーのため、Stripe には存在しない。
 *
 * 依存順序: `UserSeeder` → `MeetingPackSeeder` → 本 Seeder。
 */
final class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::query()->where('email', 'student@certify-lms.test')->first();
        if ($student === null) {
            $this->command?->warn('PaymentSeeder: 固定の受講生が存在しません。先に UserSeeder を実行してください。');

            return;
        }

        $packs = MeetingPack::query()->get()->keyBy('name');
        $onePack = $packs->get('1 回パック');
        $fivePack = $packs->get('5 回パック');
        $tenPack = $packs->get('10 回パック');
        $oldPack = $packs->get('20 回パック(旧)');
        if ($onePack === null || $fivePack === null || $tenPack === null || $oldPack === null) {
            $this->command?->warn('PaymentSeeder: 面談パックが存在しません。先に MeetingPackSeeder を実行してください。');

            return;
        }

        // 固定の受講生: 完了 / 処理中 / 失敗
        $this->createPayment($student, $onePack, PaymentStatus::Succeeded, now()->subDays(10));
        $this->createPayment($student, $fivePack, PaymentStatus::Pending, now()->subHour());
        $this->createPayment($student, $tenPack, PaymentStatus::Failed, now()->subDays(3));

        $demoStudents = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereNotIn('email', ['student@certify-lms.test', 'student-noquota@certify-lms.test'])
            ->orderBy('created_at')
            ->limit(2)
            ->get();

        // デモ受講生 1: アーカイブ済みのパックの完了
        if ($demoStudents->get(0) !== null) {
            $this->createPayment($demoStudents->get(0), $oldPack, PaymentStatus::Succeeded, now()->subDays(60));
        }

        // デモ受講生 2: 完了 / 失敗
        if ($demoStudents->get(1) !== null) {
            $this->createPayment($demoStudents->get(1), $fivePack, PaymentStatus::Succeeded, now()->subDays(5));
            $this->createPayment($demoStudents->get(1), $onePack, PaymentStatus::Failed, now()->subDays(6));
        }
    }

    /**
     * 購入時点のパックの価格・面談回数で決済レコードを作成する。完了の場合は面談回数も加算する。
     */
    private function createPayment(User $student, MeetingPack $pack, PaymentStatus $status, Carbon $purchasedAt): void
    {
        $paidAt = $status === PaymentStatus::Succeeded ? $purchasedAt->copy()->addMinute() : null;

        $payment = Payment::factory()
            ->for($student, 'user')
            ->for($pack, 'meetingPack')
            ->create([
                'stripe_checkout_session_id' => 'cs_test_seed_'.fake()->unique()->regexify('[A-Za-z0-9]{40}'),
                'status' => $status->value,
                'paid_at' => $paidAt,
                'created_at' => $purchasedAt,
                'updated_at' => $paidAt ?? $purchasedAt,
            ]);

        if ($status === PaymentStatus::Succeeded) {
            MeetingQuotaTransaction::create([
                'user_id' => $student->id,
                'type' => MeetingQuotaTransactionType::Purchased,
                'amount' => $payment->quantity,
                'related_payment_id' => $payment->id,
                'occurred_at' => $paidAt,
            ]);
        }
    }
}
