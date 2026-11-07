<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student()->inProgress(),
            'meeting_pack_id' => MeetingPack::factory()->published(),
            'stripe_checkout_session_id' => 'cs_test_'.Str::random(58),
            'amount' => function (array $attributes) {
                return MeetingPack::find($attributes['meeting_pack_id'])->price;
            },
            'quantity' => function (array $attributes) {
                return MeetingPack::find($attributes['meeting_pack_id'])->meeting_count;
            },
            'status' => PaymentStatus::Pending->value,
            'paid_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Pending->value, 'paid_at' => null]);
    }

    public function succeeded(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Succeeded->value, 'paid_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Failed->value, 'paid_at' => null]);
    }

    public function refunded(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Refunded->value, 'paid_at' => now()]);
    }
}
