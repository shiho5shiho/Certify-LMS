<?php

declare(strict_types=1);

namespace App\Notifications\Announcement;

use App\Models\Announcement;
use App\Notifications\BusinessEventNotification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * 管理者お知らせの通知。配信対象の受講生に送る。
 *
 * - 移動先は通知詳細ページ(notifications.show)。お知らせには遷移先の業務画面がないため
 * - メールには本文全文を載せる(PM 確認済)
 */
final class AdminAnnouncementNotification extends BusinessEventNotification
{
    public function __construct(private readonly Announcement $announcement) {}

    protected function notificationType(): string
    {
        return 'admin_announcement';
    }

    protected function title(): string
    {
        return $this->announcement->title;
    }

    protected function message(): string
    {
        return $this->announcement->body;
    }

    protected function url(): string
    {
        return route('notifications.show', $this->id);
    }

    /**
     * MailMessage::line() は改行を空白にまとめてしまうため、本文を 1 行ずつ渡して改行を残す。
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting('Certify LMS をご利用の皆様へ')
            ->line($this->title())
            ->lines(preg_split('/\R/', $this->message()))
            ->action('内容を確認する', $this->url())
            ->salutation('Certify LMS 運営チーム');
    }
}
