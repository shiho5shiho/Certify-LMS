<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QaThread>
 */
class QaThreadFactory extends Factory
{
    protected $model = QaThread::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'certification_id' => Certification::factory(),
            'user_id' => User::factory()->student(),
            'title' => fake()->sentence(8),
            'body' => fake()->paragraphs(3, true),
            'status' => QaThreadStatus::Unresolved,
            'resolved_at' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => QaThreadStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }
}
