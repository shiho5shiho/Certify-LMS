<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingQuota;

use App\Enums\PaymentStatus;
use App\Exceptions\MeetingQuota\StripeRequestFailedException;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use App\Services\Contracts\StripeServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('external-api')]
class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_sees_only_published_packs(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $published = MeetingPack::factory()->published()->create();
        MeetingPack::factory()->draft()->create();
        MeetingPack::factory()->archived()->create();

        // Act
        $response = $this->actingAs($student)->get(route('meeting-quota.checkout.select'));

        // Assert
        $response->assertOk();
        $response->assertViewHas('plans', fn ($plans) => $plans->pluck('id')->all() === [$published->id]);
    }

    public function test_coach_and_admin_cannot_access(): void
    {
        // Arrange
        $coach = User::factory()->coach()->inProgress()->create();
        $admin = User::factory()->admin()->create();

        // Act & Assert
        $this->actingAs($coach)->get(route('meeting-quota.checkout.select'))->assertForbidden();
        $this->actingAs($admin)->get(route('meeting-quota.checkout.select'))->assertForbidden();
    }

    public function test_graduated_student_cannot_purchase(): void
    {
        // Arrange
        $student = User::factory()->student()->graduated()->create();
        $pack = MeetingPack::factory()->published()->create();
        $this->mock(StripeServiceContract::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('createCheckoutSession');
        });

        // Act
        $response = $this->actingAs($student)->post(route('meeting-quota.checkout.create'), [
            'meeting_pack_id' => $pack->id,
        ]);

        // Assert
        $response->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_create_redirects_to_stripe_and_creates_pending_payment(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $pack = MeetingPack::factory()->published()->withCount(1)->withPrice(3000)->create();
        $this->mock(StripeServiceContract::class, function (MockInterface $mock) use ($student, $pack) {
            $mock->shouldReceive('createCheckoutSession')
                ->once()
                ->withArgs(fn (User $s, MeetingPack $p, string $successUrl, string $cancelUrl) => $s->is($student)
                    && $p->is($pack)
                    && $successUrl === route('meeting-quota.checkout.success').'?session_id={CHECKOUT_SESSION_ID}'
                    && $cancelUrl === route('dashboard.index'))
                ->andReturn(['id' => 'cs_test_123', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_123']);
        });

        // Act
        $response = $this->actingAs($student)->post(route('meeting-quota.checkout.create'), [
            'meeting_pack_id' => $pack->id,
        ]);

        // Assert
        $response->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_123');
        $this->assertDatabaseHas('payments', [
            'user_id' => $student->id,
            'meeting_pack_id' => $pack->id,
            'stripe_checkout_session_id' => 'cs_test_123',
            'amount' => 3000,
            'quantity' => 1,
            'status' => PaymentStatus::Pending->value,
            'paid_at' => null,
        ]);
    }

    public function test_create_rejects_unpublished_pack(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $draft = MeetingPack::factory()->draft()->create();
        $archived = MeetingPack::factory()->archived()->create();
        $this->mock(StripeServiceContract::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('createCheckoutSession');
        });

        foreach ([$draft, $archived] as $pack) {
            // Act
            $response = $this->actingAs($student)->post(route('meeting-quota.checkout.create'), [
                'meeting_pack_id' => $pack->id,
            ]);

            // Assert
            $response->assertSessionHasErrors('meeting_pack_id');
        }

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_create_shows_error_when_stripe_request_fails(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $pack = MeetingPack::factory()->published()->create();
        $this->mock(StripeServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('createCheckoutSession')->once()->andThrow(new StripeRequestFailedException);
        });

        // Act
        $response = $this->actingAs($student)->post(route('meeting-quota.checkout.create'), [
            'meeting_pack_id' => $pack->id,
        ]);

        // Assert
        $response->assertRedirect(route('meeting-quota.checkout.select'));
        $response->assertSessionHas('error', '決済画面を開けませんでした。時間をおいてもう一度お試しください。');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_success_shows_own_payment(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        $payment = Payment::factory()->for($student, 'user')->create(['stripe_checkout_session_id' => 'cs_test_own']);

        // Act
        $response = $this->actingAs($student)->get(route('meeting-quota.checkout.success', ['session_id' => 'cs_test_own']));

        // Assert
        $response->assertOk();
        $response->assertViewHas('payment', fn (?Payment $p) => $p?->is($payment) === true);
    }

    public function test_success_does_not_show_other_students_payment(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();
        Payment::factory()->create(['stripe_checkout_session_id' => 'cs_test_other']);

        // Act
        $response = $this->actingAs($student)->get(route('meeting-quota.checkout.success', ['session_id' => 'cs_test_other']));

        // Assert
        $response->assertOk();
        $response->assertViewHas('payment', null);
    }
}
