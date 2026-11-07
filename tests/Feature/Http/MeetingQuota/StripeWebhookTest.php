<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Stripe\Webhook;
use Tests\Support\StripeWebhookTestHelpers;
use Tests\TestCase;

#[Group('external-api')]
class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;
    use StripeWebhookTestHelpers;

    private const WEBHOOK_SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.webhook_secret' => self::WEBHOOK_SECRET]);
    }

    public function test_completed_and_paid_marks_payment_succeeded_and_grants_quota(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create(['quantity' => 5]);

        // Act
        $response = $this->postWebhook($this->stripeCheckoutEvent('checkout.session.completed', $payment->stripe_checkout_session_id, 'paid'));

        // Assert
        $response->assertNoContent();
        $payment->refresh();
        $this->assertSame(PaymentStatus::Succeeded, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $payment->user_id,
            'type' => MeetingQuotaTransactionType::Purchased->value,
            'amount' => 5,
            'related_payment_id' => $payment->id,
        ]);
    }

    public function test_completed_but_unpaid_keeps_payment_pending(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create();

        // Act
        $response = $this->postWebhook($this->stripeCheckoutEvent('checkout.session.completed', $payment->stripe_checkout_session_id, 'unpaid'));

        // Assert
        $response->assertNoContent();
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_async_payment_succeeded_marks_payment_succeeded_and_grants_quota(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create();

        // Act
        $response = $this->postWebhook($this->stripeCheckoutEvent('checkout.session.async_payment_succeeded', $payment->stripe_checkout_session_id, 'paid'));

        // Assert
        $response->assertNoContent();
        $this->assertSame(PaymentStatus::Succeeded, $payment->refresh()->status);
        $this->assertSame(1, MeetingQuotaTransaction::where('related_payment_id', $payment->id)->count());
    }

    public function test_async_payment_failed_and_expired_mark_payment_failed(): void
    {
        foreach (['checkout.session.async_payment_failed', 'checkout.session.expired'] as $type) {
            // Arrange
            $payment = Payment::factory()->pending()->create();

            // Act
            $response = $this->postWebhook($this->stripeCheckoutEvent($type, $payment->stripe_checkout_session_id, 'unpaid'));

            // Assert
            $response->assertNoContent();
            $this->assertSame(PaymentStatus::Failed, $payment->refresh()->status);
        }

        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_duplicated_notification_grants_quota_only_once(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create();
        $event = $this->stripeCheckoutEvent('checkout.session.completed', $payment->stripe_checkout_session_id, 'paid');

        // Act
        $this->postWebhook($event)->assertNoContent();
        $this->postWebhook($event)->assertNoContent();

        // Assert
        $this->assertSame(PaymentStatus::Succeeded, $payment->refresh()->status);
        $this->assertSame(1, MeetingQuotaTransaction::where('related_payment_id', $payment->id)->count());
    }

    public function test_completed_and_async_succeeded_for_same_payment_grant_quota_only_once(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create();

        // Act: 決済完了と支払い確定の通知が、同じ決済について両方届く
        $this->postWebhook($this->stripeCheckoutEvent('checkout.session.completed', $payment->stripe_checkout_session_id, 'paid'))->assertNoContent();
        $this->postWebhook($this->stripeCheckoutEvent('checkout.session.async_payment_succeeded', $payment->stripe_checkout_session_id, 'paid'))->assertNoContent();

        // Assert
        $this->assertSame(PaymentStatus::Succeeded, $payment->refresh()->status);
        $this->assertSame(1, MeetingQuotaTransaction::where('related_payment_id', $payment->id)->count());
    }

    public function test_expired_after_succeeded_does_not_change_status(): void
    {
        // Arrange
        $payment = Payment::factory()->succeeded()->create();

        // Act
        $response = $this->postWebhook($this->stripeCheckoutEvent('checkout.session.expired', $payment->stripe_checkout_session_id, 'unpaid'));

        // Assert
        $response->assertNoContent();
        $this->assertSame(PaymentStatus::Succeeded, $payment->refresh()->status);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create();

        // Act
        $response = $this->postWebhook(
            $this->stripeCheckoutEvent('checkout.session.completed', $payment->stripe_checkout_session_id, 'paid'),
            't='.time().',v1=forged-signature',
        );

        // Assert
        $response->assertStatus(400);
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_rejected_when_webhook_secret_is_not_set(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create();
        $event = $this->stripeCheckoutEvent('checkout.session.completed', $payment->stripe_checkout_session_id, 'paid');
        $signature = $this->stripeSignature(json_encode($event, JSON_THROW_ON_ERROR), self::WEBHOOK_SECRET);
        config(['services.stripe.webhook_secret' => null]);

        // Act
        $response = $this->postWebhook($event, $signature);

        // Assert
        $response->assertStatus(400);
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
    }

    public function test_missing_signature_header_is_rejected(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create();
        $payload = json_encode($this->stripeCheckoutEvent('checkout.session.completed', $payment->stripe_checkout_session_id, 'paid'), JSON_THROW_ON_ERROR);

        // Act
        $response = $this->postStripeWebhook($payload, null);

        // Assert
        $response->assertStatus(400);
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_signature_with_other_secret_is_rejected(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create();
        $payload = json_encode($this->stripeCheckoutEvent('checkout.session.completed', $payment->stripe_checkout_session_id, 'paid'), JSON_THROW_ON_ERROR);

        // Act
        $response = $this->postStripeWebhook($payload, $this->stripeSignature($payload, 'whsec_other_secret'));

        // Assert
        $response->assertStatus(400);
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
    }

    public function test_tampered_payload_is_rejected(): void
    {
        // Arrange: 未払い(unpaid)の通知に付いた正規の署名のまま、本文だけ支払い済み(paid)に書き換える
        $payment = Payment::factory()->pending()->create();
        $signedPayload = json_encode($this->stripeCheckoutEvent('checkout.session.completed', $payment->stripe_checkout_session_id, 'unpaid'), JSON_THROW_ON_ERROR);
        $tamperedPayload = json_encode($this->stripeCheckoutEvent('checkout.session.completed', $payment->stripe_checkout_session_id, 'paid'), JSON_THROW_ON_ERROR);

        // Act
        $response = $this->postStripeWebhook($tamperedPayload, $this->stripeSignature($signedPayload, self::WEBHOOK_SECRET));

        // Assert
        $response->assertStatus(400);
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_signature_older_than_tolerance_is_rejected(): void
    {
        // Arrange: Stripe SDK は、署名のタイムスタンプが許容時間(300 秒)より古い通知を拒否する
        $payment = Payment::factory()->pending()->create();
        $payload = json_encode($this->stripeCheckoutEvent('checkout.session.completed', $payment->stripe_checkout_session_id, 'paid'), JSON_THROW_ON_ERROR);
        $timestamp = time() - Webhook::DEFAULT_TOLERANCE - 10;

        // Act
        $response = $this->postStripeWebhook($payload, $this->stripeSignature($payload, self::WEBHOOK_SECRET, $timestamp));

        // Assert
        $response->assertStatus(400);
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
    }

    public function test_signature_within_tolerance_is_accepted(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create();
        $payload = json_encode($this->stripeCheckoutEvent('checkout.session.completed', $payment->stripe_checkout_session_id, 'paid'), JSON_THROW_ON_ERROR);
        $timestamp = time() - Webhook::DEFAULT_TOLERANCE + 10;

        // Act
        $response = $this->postStripeWebhook($payload, $this->stripeSignature($payload, self::WEBHOOK_SECRET, $timestamp));

        // Assert
        $response->assertNoContent();
        $this->assertSame(PaymentStatus::Succeeded, $payment->refresh()->status);
    }

    public function test_unexpected_event_is_ignored(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create();

        // Act
        $response = $this->postWebhook($this->stripeCheckoutEvent('customer.created', $payment->stripe_checkout_session_id, 'paid'));

        // Assert
        $response->assertNoContent();
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_unknown_checkout_session_is_ignored(): void
    {
        // Act
        $response = $this->postWebhook($this->stripeCheckoutEvent('checkout.session.completed', 'cs_test_unknown', 'paid'));

        // Assert
        $response->assertNoContent();
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    /**
     * イベントを JSON にして Webhook を送る。署名を省略すると正規の署名を付ける。
     *
     * @param array<string, mixed> $event
     */
    private function postWebhook(array $event, ?string $signature = null): TestResponse
    {
        $payload = json_encode($event, JSON_THROW_ON_ERROR);

        return $this->postStripeWebhook($payload, $signature ?? $this->stripeSignature($payload, self::WEBHOOK_SECRET));
    }
}
