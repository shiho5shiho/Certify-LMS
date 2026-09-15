<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\MeetingPack;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 面談パック新規作成 StoreRequest のバリデーション検証。
 * 必須 name / meeting_count(1-100) / price(0-1,000,000) / 任意 description・stripe_price_id を網羅し、
 * authorize は admin のみ true を検証する。
 */
class StoreRequestTest extends TestCase
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
            'name' => '5 回パック',
            'description' => '説明文',
            'meeting_count' => 5,
            'price' => 12000,
            'stripe_price_id' => null,
            'sort_order' => 0,
        ], $override);
    }

    public function test_validation_passes_with_valid_payload(): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.store'), $this->payload());

        // Assert
        $response->assertSessionDoesntHaveErrors();
        $response->assertStatus(302);
        $this->assertDatabaseHas('meeting_packs', ['name' => '5 回パック', 'status' => 'draft']);
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $overrides, string $expectedErrorField): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.meeting-packs.store'), $this->payload($overrides));

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    public function test_authorize_returns_false_for_non_admin(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();

        // Act
        $response = $this->actingAs($coach)->postJson(route('admin.meeting-packs.store'), $this->payload());

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
            'name 101 文字で 422' => [['name' => str_repeat('a', 101)], 'name'],
            'meeting_count 未指定で 422' => [['meeting_count' => null], 'meeting_count'],
            'meeting_count 0 で 422' => [['meeting_count' => 0], 'meeting_count'],
            'meeting_count 101 で 422' => [['meeting_count' => 101], 'meeting_count'],
            'price 未指定で 422' => [['price' => null], 'price'],
            'price マイナスで 422' => [['price' => -1], 'price'],
            'price 1,000,001 で 422' => [['price' => 1_000_001], 'price'],
            'description 2001 文字で 422' => [['description' => str_repeat('b', 2001)], 'description'],
            'stripe_price_id 256 文字で 422' => [['stripe_price_id' => str_repeat('c', 256)], 'stripe_price_id'],
        ];
    }
}
