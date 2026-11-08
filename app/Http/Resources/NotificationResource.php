<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * 通知ポップオーバーの 1 行分の Resource。
 * タイトル・プレビュー・経過時間の作り方は、フルページの通知行(notifications/_partials/notification-row)に揃える。
 *
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->data['title'] ?? '通知',
            'message' => $this->data['message'] ?? ($this->data['body_preview'] ?? ''),
            'url' => $this->data['url'] ?? route('notifications.index'),
            'is_read' => $this->read_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at?->diffForHumans() ?? '',
        ];
    }
}
