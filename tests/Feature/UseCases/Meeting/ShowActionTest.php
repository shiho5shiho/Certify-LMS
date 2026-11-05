<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Meeting;
use App\UseCases\Meeting\ShowAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_loads_relations_for_detail_page(): void
    {
        $meeting = Meeting::factory()->reserved()->create()->fresh();

        $result = app(ShowAction::class)($meeting);

        foreach (['enrollment', 'coach', 'student', 'canceledBy', 'meetingMemo'] as $relation) {
            $this->assertTrue($result->relationLoaded($relation), "{$relation} が読み込まれていません");
        }
    }
}
