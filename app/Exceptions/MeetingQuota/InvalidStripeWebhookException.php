<?php

declare(strict_types=1);

namespace App\Exceptions\MeetingQuota;

use RuntimeException;
use Throwable;

/**
 * Stripe からの Webhook の署名を検証できなかった場合の例外(署名不正 / 改ざん / 署名シークレット未設定など)。
 *
 * 呼び出し側で通知を処理せずに 400 を返す。
 */
final class InvalidStripeWebhookException extends RuntimeException
{
    public function __construct(string $message = 'Stripe の Webhook の署名を検証できませんでした。', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
