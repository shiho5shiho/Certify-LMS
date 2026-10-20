<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->realText(30),
            'body' => fake()->realText(200),
            'target_type' => AnnouncementTargetType::AllStudents,
            'dispatched_count' => 1,
            'dispatched_at' => now(),
            'created_by_user_id' => User::factory()->admin(),
        ];
    }
}
