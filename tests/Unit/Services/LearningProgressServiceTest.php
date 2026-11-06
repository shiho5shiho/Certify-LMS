<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Services\LearningProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    private LearningProgressService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LearningProgressService::class);
    }

    public function test_summarize_counts_only_published_contents(): void
    {
        $enrollment = Enrollment::factory()->create();
        $part = Part::factory()->published()->forCertification($enrollment->certification)->create();
        $chapterDone = Chapter::factory()->published()->forPart($part)->create();
        $chapterNotDone = Chapter::factory()->published()->forPart($part)->create();

        $readSections = Section::factory()->count(2)->published()->forChapter($chapterDone)->create();
        Section::factory()->draft()->forChapter($chapterDone)->create();
        Section::factory()->published()->forChapter($chapterNotDone)->create();

        // 非公開の Part 配下は数えない
        $draftPart = Part::factory()->draft()->forCertification($enrollment->certification)->create();
        $draftChapter = Chapter::factory()->published()->forPart($draftPart)->create();
        Section::factory()->published()->forChapter($draftChapter)->create();

        foreach ($readSections as $section) {
            SectionProgress::factory()->forEnrollment($enrollment)->forSection($section)->create();
        }

        $summary = $this->service->summarize($enrollment);

        $this->assertSame(3, $summary->sectionsTotal);
        $this->assertSame(2, $summary->sectionsCompleted);
        $this->assertSame(0.6667, $summary->sectionCompletionRatio);
        $this->assertSame(2, $summary->chaptersTotal);
        $this->assertSame(1, $summary->chaptersCompleted);
        $this->assertSame(0.5, $summary->chapterCompletionRatio);
        $this->assertSame(1, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted);
        $this->assertSame(0.0, $summary->partCompletionRatio);
        $this->assertSame(0.6667, $summary->overallCompletionRatio);
    }

    public function test_summarize_marks_part_completed_when_all_sections_read(): void
    {
        $enrollment = Enrollment::factory()->create();
        $part = Part::factory()->published()->forCertification($enrollment->certification)->create();
        $chapter = Chapter::factory()->published()->forPart($part)->create();
        $section = Section::factory()->published()->forChapter($chapter)->create();
        SectionProgress::factory()->forEnrollment($enrollment)->forSection($section)->create();

        $summary = $this->service->summarize($enrollment);

        $this->assertSame(1, $summary->chaptersCompleted);
        $this->assertSame(1, $summary->partsCompleted);
        $this->assertSame(1.0, $summary->overallCompletionRatio);
    }

    public function test_summarize_returns_zero_when_no_published_contents(): void
    {
        $enrollment = Enrollment::factory()->create();

        $summary = $this->service->summarize($enrollment);

        $this->assertSame(0, $summary->sectionsTotal);
        $this->assertSame(0, $summary->chaptersTotal);
        $this->assertSame(0, $summary->partsTotal);
        $this->assertSame(0.0, $summary->overallCompletionRatio);
    }

    public function test_summarize_ignores_progress_of_other_enrollment(): void
    {
        $enrollment = Enrollment::factory()->create();
        $other = Enrollment::factory()->create(['certification_id' => $enrollment->certification_id]);
        $part = Part::factory()->published()->forCertification($enrollment->certification)->create();
        $chapter = Chapter::factory()->published()->forPart($part)->create();
        $section = Section::factory()->published()->forChapter($chapter)->create();
        SectionProgress::factory()->forEnrollment($other)->forSection($section)->create();

        $summary = $this->service->summarize($enrollment);

        $this->assertSame(1, $summary->sectionsTotal);
        $this->assertSame(0, $summary->sectionsCompleted);
    }

    public function test_section_completion_ratios_returns_ratio_per_enrollment(): void
    {
        $enrollmentA = Enrollment::factory()->create();
        $enrollmentB = Enrollment::factory()->create(['certification_id' => $enrollmentA->certification_id]);
        $part = Part::factory()->published()->forCertification($enrollmentA->certification)->create();
        $chapter = Chapter::factory()->published()->forPart($part)->create();
        $sections = Section::factory()->count(2)->published()->forChapter($chapter)->create();
        SectionProgress::factory()->forEnrollment($enrollmentA)->forSection($sections[0])->create();

        $ratios = $this->service->sectionCompletionRatios(collect([$enrollmentA, $enrollmentB]));

        $this->assertSame(0.5, $ratios[$enrollmentA->id]);
        $this->assertSame(0.0, $ratios[$enrollmentB->id]);
    }

    public function test_section_completion_ratios_returns_empty_when_no_enrollments(): void
    {
        $this->assertSame([], $this->service->sectionCompletionRatios(collect()));
    }

    public function test_completed_section_counts_by_chapter(): void
    {
        $enrollment = Enrollment::factory()->create();
        $part = Part::factory()->published()->forCertification($enrollment->certification)->create();
        $chapterRead = Chapter::factory()->published()->forPart($part)->create();
        $chapterUnread = Chapter::factory()->published()->forPart($part)->create();
        $sections = Section::factory()->count(2)->published()->forChapter($chapterRead)->create();
        Section::factory()->published()->forChapter($chapterUnread)->create();
        SectionProgress::factory()->forEnrollment($enrollment)->forSection($sections[0])->create();

        $counts = $this->service->completedSectionCountsByChapter(
            $enrollment,
            collect([$chapterRead->id, $chapterUnread->id]),
        );

        $this->assertSame(1, $counts[$chapterRead->id]);
        $this->assertArrayNotHasKey($chapterUnread->id, $counts);
    }
}
