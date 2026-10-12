<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use App\Http\Controllers\NotificationController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 自分宛の通知一覧リクエスト。tab(全件 / 未読のみ)のクエリ受け取りのみを行う。
 * 通知は全ロール共通で自分宛のものだけを扱うため、認可はログインしていることのみ。
 *
 * @see NotificationController::index()
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', 'in:all,unread'],
        ];
    }
}
