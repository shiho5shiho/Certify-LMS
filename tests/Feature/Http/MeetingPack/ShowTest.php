<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_latest_20_payments_with_purchaser(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();
        $pack = MeetingPack::factory()->published()->create();
        Payment::factory()->count(21)->for($pack, 'meetingPack')->for($student, 'user')->create();

        // Act
        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.show', $pack));

        // Assert
        $response->assertOk();
        $response->assertViewHas('plan', fn (MeetingPack $plan) => $plan->payments->count() === 20);
        $response->assertSee($student->email);
    }
}
