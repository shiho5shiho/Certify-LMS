<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 業務イベント(チャット / 質問掲示板 / 面談)の通知の共通親クラス。
 *
 * アプリ内通知(database)とメール(mail)に同時に送る。
 * 子クラスは種別・タイトル・本文・移動先 URL の 4 つを返すだけにする。
 * 保存するキー(notification_type / title / message / url)は notifications/_partials/notification-row.blade.php が読む形に合わせる。
 *
 * 送信はキュー経由で行う(ShouldQueue)。worker が「受信者 × チャネル」ごとに 1 ジョブずつ送るため、1 件の送信失敗がほかの受信者・チャネルに影響しない。
 * 一時的な失敗は 10 秒 → 1 分 → 5 分の間隔で最大 3 回リトライし、それでも失敗したら failed_jobs に記録する。
 */
abstract class BusinessEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * 試行回数の上限(初回 + リトライ 3 回)。
     */
    public int $tries = 4;

    /**
     * リトライまでの待機秒数(1 回目 / 2 回目 / 3 回目)。
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    abstract protected function notificationType(): string;

    abstract protected function title(): string;

    abstract protected function message(): string;

    abstract protected function url(): string;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array{notification_type: string, title: string, message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'notification_type' => $this->notificationType(),
            'title' => $this->title(),
            'message' => $this->message(),
            'url' => $this->url(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting('Certify LMS をご利用の皆様へ')
            ->line($this->title())
            ->line($this->message())
            ->action('内容を確認する', $this->url())
            ->salutation('Certify LMS 運営チーム');
    }
}
