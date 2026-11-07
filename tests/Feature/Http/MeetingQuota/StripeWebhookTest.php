<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

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
        $response = $this->postWebhook($this->event('checkout.session.completed', $payment->stripe_checkout_session_id, 'paid'));

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
        $response = $this->postWebhook($this->event('checkout.session.completed', $payment->stripe_checkout_session_id, 'unpaid'));

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
        $response = $this->postWebhook($this->event('checkout.session.async_payment_succeeded', $payment->stripe_checkout_session_id, 'paid'));

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
            $response = $this->postWebhook($this->event($type, $payment->stripe_checkout_session_id, 'unpaid'));

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
        $event = $this->event('checkout.session.completed', $payment->stripe_checkout_session_id, 'paid');

        // Act
        $this->postWebhook($event)->assertNoContent();
        $this->postWebhook($event)->assertNoContent();

        // Assert
        $this->assertSame(PaymentStatus::Succeeded, $payment->refresh()->status);
        $this->assertSame(1, MeetingQuotaTransaction::where('related_payment_id', $payment->id)->count());
    }

    public function test_expired_after_succeeded_does_not_change_status(): void
    {
        // Arrange
        $payment = Payment::factory()->succeeded()->create();

        // Act
        $response = $this->postWebhook($this->event('checkout.session.expired', $payment->stripe_checkout_session_id, 'unpaid'));

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
            $this->event('checkout.session.completed', $payment->stripe_checkout_session_id, 'paid'),
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
        $event = $this->event('checkout.session.completed', $payment->stripe_checkout_session_id, 'paid');
        $signature = $this->sign(json_encode($event, JSON_THROW_ON_ERROR));
        config(['services.stripe.webhook_secret' => null]);

        // Act
        $response = $this->postWebhook($event, $signature);

        // Assert
        $response->assertStatus(400);
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
    }

    public function test_unexpected_event_is_ignored(): void
    {
        // Arrange
        $payment = Payment::factory()->pending()->create();

        // Act
        $response = $this->postWebhook($this->event('customer.created', $payment->stripe_checkout_session_id, 'paid'));

        // Assert
        $response->assertNoContent();
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_unknown_checkout_session_is_ignored(): void
    {
        // Act
        $response = $this->postWebhook($this->event('checkout.session.completed', 'cs_test_unknown', 'paid'));

        // Assert
        $response->assertNoContent();
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    /**
     * Stripe と同じ形式のイベントを組み立てる(決済画面の ID と支払い状態のみ)。
     *
     * @return array<string, mixed>
     */
    private function event(string $type, string $checkoutSessionId, string $paymentStatus): array
    {
        return [
            'id' => 'evt_test_'.Str::random(16),
            'object' => 'event',
            'type' => $type,
            'data' => [
                'object' => [
                    'id' => $checkoutSessionId,
                    'object' => 'checkout.session',
                    'payment_status' => $paymentStatus,
                ],
            ],
        ];
    }

    /**
     * Stripe と同じ方法で署名ヘッダーを作る(「タイムスタンプ.本文」の HMAC-SHA256)。
     */
    private function sign(string $payload): string
    {
        $timestamp = time();

        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, self::WEBHOOK_SECRET);
    }

    /**
     * @param array<string, mixed> $event
     */
    private function postWebhook(array $event, ?string $signature = null): TestResponse
    {
        $payload = json_encode($event, JSON_THROW_ON_ERROR);

        return $this->call('POST', route('webhooks.stripe'), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature ?? $this->sign($payload),
        ], content: $payload);
    }
}
