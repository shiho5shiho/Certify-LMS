<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Exceptions\Mentoring\GoogleCalendarRequestFailedException;
use App\Models\GoogleCalendarCredential;
use App\Models\User;
use App\Services\Contracts\GoogleCalendarServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('external-api')]
class GoogleCalendarControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_connect_redirects_to_google_with_state_saved_in_session(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $this->mock(GoogleCalendarServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('authorizationUrl')->once()->andReturn('https://accounts.google.com/o/oauth2/v2/auth?dummy');
        });

        $response = $this->actingAs($coach)->get(route('settings.google-calendar.redirect'));

        $response->assertRedirect('https://accounts.google.com/o/oauth2/v2/auth?dummy');
        $response->assertSessionHas('google_calendar.state');
    }

    public function test_callback_saves_credential_when_state_matches(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $this->mock(GoogleCalendarServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('exchangeCode')->once()->with('auth-code')->andReturn([
                'calendar_id' => 'coach@example.com',
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'token_expires_at' => now()->addHour(),
            ]);
        });

        $response = $this->actingAs($coach)
            ->withSession(['google_calendar.state' => 'valid-state'])
            ->get(route('settings.google-calendar.callback', ['state' => 'valid-state', 'code' => 'auth-code']));

        $response->assertRedirect(route('settings.availability.index'));
        $response->assertSessionHas('success', 'Googleカレンダーと連携しました。');
        $this->assertDatabaseHas('google_calendar_credentials', [
            'user_id' => $coach->id,
            'calendar_id' => 'coach@example.com',
        ]);
    }

    public function test_callback_rejects_mismatched_state(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $this->mock(GoogleCalendarServiceContract::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('exchangeCode');
        });

        $response = $this->actingAs($coach)
            ->withSession(['google_calendar.state' => 'valid-state'])
            ->get(route('settings.google-calendar.callback', ['state' => 'forged-state', 'code' => 'attacker-code']));

        $response->assertRedirect(route('settings.availability.index'));
        $response->assertSessionHas('error', 'Googleカレンダーとの連携に失敗しました。もう一度お試しください。');
        $this->assertDatabaseCount('google_calendar_credentials', 0);
    }

    public function test_callback_shows_error_when_token_exchange_fails(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $this->mock(GoogleCalendarServiceContract::class, function (MockInterface $mock) {
            $mock->shouldReceive('exchangeCode')->once()->andThrow(new GoogleCalendarRequestFailedException);
        });

        $response = $this->actingAs($coach)
            ->withSession(['google_calendar.state' => 'valid-state'])
            ->get(route('settings.google-calendar.callback', ['state' => 'valid-state', 'code' => 'auth-code']));

        $response->assertRedirect(route('settings.availability.index'));
        $response->assertSessionHas('error', 'Googleカレンダーとの連携に失敗しました。もう一度お試しください。');
        $this->assertDatabaseCount('google_calendar_credentials', 0);
    }

    public function test_callback_does_not_exchange_when_coach_denies_consent(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $this->mock(GoogleCalendarServiceContract::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('exchangeCode');
        });

        // 同意画面で拒否すると、code の代わりに error が付いて戻ってくる
        $response = $this->actingAs($coach)
            ->withSession(['google_calendar.state' => 'valid-state'])
            ->get(route('settings.google-calendar.callback', ['state' => 'valid-state', 'error' => 'access_denied']));

        $response->assertRedirect(route('settings.availability.index'));
        $response->assertSessionHas('error', 'Googleカレンダーとの連携に失敗しました。もう一度お試しください。');
        $this->assertDatabaseCount('google_calendar_credentials', 0);
    }

    public function test_destroy_deletes_credential(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        GoogleCalendarCredential::factory()->forCoach($coach)->create();

        $response = $this->actingAs($coach)->delete(route('settings.google-calendar.destroy'));

        $response->assertRedirect(route('settings.availability.index'));
        $response->assertSessionHas('success', 'Googleカレンダーとの連携を解除しました。');
        $this->assertDatabaseCount('google_calendar_credentials', 0);
    }

    public function test_student_cannot_connect(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)
            ->get(route('settings.google-calendar.redirect'))
            ->assertForbidden();
    }
}
