<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use Stripe\Checkout\Session;
use Stripe\Event;

/**
 * 署名検証済みの Stripe のイベントを種類ごとに振り分けるユースケース。
 *
 * 決済画面(Checkout Session)の完了 / 支払い確定 / 支払い失敗 / 期限切れのみ処理し、
 * それ以外のイベント(返金を含む)は何もしない。
 */
final class HandleStripeWebhookAction
{
    public function __construct(
        private readonly CompletePaymentAction $completePayment,
        private readonly FailPaymentAction $failPayment,
    ) {}

    public function __invoke(Event $event): void
    {
        $session = $event->data->object;

        switch ($event->type) {
            case Event::CHECKOUT_SESSION_COMPLETED:
                // コンビニ払いなど支払いが後から確定する決済方法は unpaid で届くため、処理中のままにする
                if ($session->payment_status === Session::PAYMENT_STATUS_PAID) {
                    ($this->completePayment)($session->id);
                }
                break;

            case Event::CHECKOUT_SESSION_ASYNC_PAYMENT_SUCCEEDED:
                ($this->completePayment)($session->id);
                break;

            case Event::CHECKOUT_SESSION_ASYNC_PAYMENT_FAILED:
            case Event::CHECKOUT_SESSION_EXPIRED:
                ($this->failPayment)($session->id);
                break;

            default:
                // 想定外のイベントは何もしない
                break;
        }
    }
}
