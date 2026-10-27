<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MockExam;

use App\Models\Certification;
use App\Models\MockExam;
use App\Models\MockExamQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 模試マスタ一覧 (`admin.mock-exams.index`) の表示内容を検証する Feature テスト。
 * クエリ最適化 (Eager Loading / withCount) の前後で、並び順・表示範囲が変わらないこと、
 * 問題数が実件数で表示されることを担保する。
 */
class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_actual_question_count(): void
    {
        $admin = User::factory()->admin()->create();
        $mockExam = MockExam::factory()->create();
        MockExamQuestion::factory()->count(3)->forMockExam($mockExam)->create();

        $response = $this->actingAs($admin)->get(route('admin.mock-exams.index'));

        $response->assertOk();
        $this->assertSame(3, $response->viewData('mockExams')->first()->mock_exam_questions_count);
    }

    public function test_index_keeps_sort_order_by_order_then_updated_at_desc(): void
    {
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();
        $second = MockExam::factory()->forCertification($certification)
            ->create(['order' => 1, 'updated_at' => now()->subDays(2)]);
        $first = MockExam::factory()->forCertification($certification)
            ->create(['order' => 0, 'updated_at' => now()->subDays(3)]);
        $third = MockExam::factory()->forCertification($certification)
            ->create(['order' => 1, 'updated_at' => now()->subDays(5)]);

        $response = $this->actingAs($admin)->get(route('admin.mock-exams.index'));

        $response->assertOk();
        $this->assertSame(
            [$first->id, $second->id, $third->id],
            $response->viewData('mockExams')->pluck('id')->all(),
        );
    }

    public function test_coach_sees_only_mock_exams_of_assigned_certifications(): void
    {
        $coach = User::factory()->coach()->create();
        $assigned = Certification::factory()->published()->create();
        $unassigned = Certification::factory()->published()->create();
        $assigned->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
        $visible = MockExam::factory()->forCertification($assigned)->create();
        MockExam::factory()->forCertification($unassigned)->create();

        $response = $this->actingAs($coach)->get(route('admin.mock-exams.index'));

        $response->assertOk();
        $this->assertSame(
            [$visible->id],
            $response->viewData('mockExams')->pluck('id')->all(),
        );
    }
}
