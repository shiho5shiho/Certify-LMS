<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => '更新後パック',
            'description' => '説明文',
            'meeting_count' => 3,
            'price' => 9000,
            'stripe_price_id' => null,
            'sort_order' => 0,
        ], $override);
    }

    public function test_validation_passes_with_valid_payload(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->draft()->create([
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        // Act
        $response = $this->actingAs($admin)->patch(route('admin.meeting-packs.update', $pack), $this->payload());

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('admin.meeting-packs.show', $pack));
        $this->assertDatabaseHas('meeting_packs', ['id' => $pack->id, 'name' => '更新後パック']);
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $overrides, string $expectedErrorField): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->draft()->create();

        // Act
        $response = $this->actingAs($admin)->patchJson(route('admin.meeting-packs.update', $pack), $this->payload($overrides));

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    public function test_authorize_returns_false_for_non_admin(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $pack = MeetingPack::factory()->draft()->create();

        // Act
        $response = $this->actingAs($coach)->patchJson(route('admin.meeting-packs.update', $pack), $this->payload());

        // Assert
        $response->assertForbidden();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'name 未指定で 422' => [['name' => ''], 'name'],
            'meeting_count 101 で 422' => [['meeting_count' => 101], 'meeting_count'],
            'price マイナスで 422' => [['price' => -1], 'price'],
        ];
    }
}
