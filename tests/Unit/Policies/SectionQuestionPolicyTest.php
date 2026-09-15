<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionQuestion;
use App\Models\User;
use App\Policies\SectionQuestionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SectionQuestionPolicy の判定を検証する Unit テスト。
 * admin / coach 担当 / student 受講中 + Published のロール分岐を網羅する。
 */
class SectionQuestionPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_full_access(): void
    {
        $admin = User::factory()->admin()->create();
        $question = SectionQuestion::factory()->published()->create();
        $section = $question->section;
        $policy = new SectionQuestionPolicy;

        $this->assertTrue($policy->viewAny($admin, $section));
        $this->assertTrue($policy->view($admin, $question));
        $this->assertTrue($policy->update($admin, $question));
    }

    public function test_student_with_enrollment_can_view_published_question(): void
    {
        $student = User::factory()->student()->create();
        $question = SectionQuestion::factory()->published()->create();
        $cert = $question->section->chapter->part->certification;
        Enrollment::factory()->for($student)->for($cert)->learning()->create();
        $policy = new SectionQuestionPolicy;

        $this->assertTrue($policy->view($student, $question));
    }

    public function test_student_without_enrollment_cannot_view(): void
    {
        $student = User::factory()->student()->create();
        $question = SectionQuestion::factory()->published()->create();
        $policy = new SectionQuestionPolicy;

        $this->assertFalse($policy->view($student, $question), '未受講の student は view 不可');
    }

    public function test_student_cannot_view_draft_question(): void
    {
        $student = User::factory()->student()->create();
        $question = SectionQuestion::factory()->draft()->create();
        $cert = $question->section->chapter->part->certification;
        Enrollment::factory()->for($student)->for($cert)->learning()->create();
        $policy = new SectionQuestionPolicy;

        $this->assertFalse($policy->view($student, $question), 'draft の question は受講中でも閲覧不可');
    }

    public function test_coach_assigned_only(): void
    {
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $assignedCert = Certification::factory()->published()->create();
        $otherCert = Certification::factory()->published()->create();
        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $assignedCert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $assignedSection = Section::factory()->for(
            Chapter::factory()->for(Part::factory()->for($assignedCert))
        )->create();
        $assignedQuestion = SectionQuestion::factory()->published()->for($assignedSection)->create();

        $otherSection = Section::factory()->for(
            Chapter::factory()->for(Part::factory()->for($otherCert))
        )->create();
        $otherQuestion = SectionQuestion::factory()->published()->for($otherSection)->create();

        $policy = new SectionQuestionPolicy;

        $this->assertTrue($policy->viewAny($coach, $assignedSection), 'coachは担当資格配下の演習問題一覧を閲覧できるはず');
        $this->assertFalse($policy->viewAny($coach, $otherSection), 'coachは非担当資格配下の演習問題一覧を閲覧できないはず');
        $this->assertTrue($policy->view($coach, $assignedQuestion), 'coachは担当資格の演習問題を閲覧できるはず');
        $this->assertFalse($policy->view($coach, $otherQuestion), 'coachは非担当資格の演習問題を閲覧できないはず');
        $this->assertTrue($policy->update($coach, $assignedQuestion));
        $this->assertFalse($policy->update($coach, $otherQuestion), 'coachは非担当資格の演習問題を更新できないはず');
    }
}
