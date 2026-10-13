<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 業務イベント(チャット / 質問掲示板 / 面談)の通知の共通親クラス。
 *
 * アプリ内通知(database)とメール(mail)に同時に送る。
 * 子クラスは種別・タイトル・本文・移動先 URL の 4 つを返すだけにする。
 * 保存するキー(notification_type / title / message / url)は notifications/_partials/notification-row.blade.php が読む形に合わせる。
 */
abstract class BusinessEventNotification extends Notification
{
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
