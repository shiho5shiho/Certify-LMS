<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GoogleCalendarCredential;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleCalendarCredential>
 */
class GoogleCalendarCredentialFactory extends Factory
{
    protected $model = GoogleCalendarCredential::class;

    /**
     * Google API には通らないダミーの認証情報(テスト・初期データ用)。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->coach(),
            'calendar_id' => fake()->unique()->safeEmail(),
            'access_token' => 'dummy-access-token',
            'refresh_token' => 'dummy-refresh-token',
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ];
    }

    public function forCoach(User $coach): static
    {
        return $this->state(fn () => [
            'user_id' => $coach->id,
        ]);
    }
}
