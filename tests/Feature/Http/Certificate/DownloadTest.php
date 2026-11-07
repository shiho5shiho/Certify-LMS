<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Certificate;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
    }

    private function createCertificate(User $student, Certification $certification, bool $withPdf = true): Certificate
    {
        $enrollment = Enrollment::factory()->for($student)->for($certification)->passed()->create();
        $certificate = Certificate::factory()->forEnrollment($enrollment)->create();

        if ($withPdf) {
            Storage::disk('private')->put($certificate->pdf_path, '%PDF-dummy');
        }

        return $certificate;
    }

    private function attachCoach(Certification $certification, User $coach): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    public function test_student_can_download_own_certificate(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certificate = $this->createCertificate($student, Certification::factory()->published()->create());

        $this->actingAs($student)
            ->get(route('certificates.download', $certificate))
            ->assertOk()
            ->assertDownload("certificate-{$certificate->id}.pdf");
    }

    public function test_graduated_student_can_download_own_certificate(): void
    {
        $student = User::factory()->student()->graduated()->create();
        $certificate = $this->createCertificate($student, Certification::factory()->published()->create());

        $this->actingAs($student)
            ->get(route('certificates.download', $certificate))
            ->assertOk()
            ->assertDownload("certificate-{$certificate->id}.pdf");
    }

    public function test_student_cannot_download_other_students_certificate(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $certificate = $this->createCertificate($owner, Certification::factory()->published()->create());

        $this->actingAs($other)
            ->get(route('certificates.download', $certificate))
            ->assertForbidden();
    }

    public function test_assigned_coach_can_download_certificate(): void
    {
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach);
        $certificate = $this->createCertificate(User::factory()->student()->inProgress()->create(), $certification);

        $this->actingAs($coach)
            ->get(route('certificates.download', $certificate))
            ->assertOk()
            ->assertDownload("certificate-{$certificate->id}.pdf");
    }

    public function test_unassigned_coach_cannot_download_certificate(): void
    {
        $coach = User::factory()->coach()->create();
        $certificate = $this->createCertificate(
            User::factory()->student()->inProgress()->create(),
            Certification::factory()->published()->create(),
        );

        $this->actingAs($coach)
            ->get(route('certificates.download', $certificate))
            ->assertForbidden();
    }

    public function test_admin_can_download_any_certificate(): void
    {
        $admin = User::factory()->admin()->create();
        $certificate = $this->createCertificate(
            User::factory()->student()->inProgress()->create(),
            Certification::factory()->published()->create(),
        );

        $this->actingAs($admin)
            ->get(route('certificates.download', $certificate))
            ->assertOk()
            ->assertDownload("certificate-{$certificate->id}.pdf");
    }

    public function test_returns_404_when_pdf_file_is_missing(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certificate = $this->createCertificate($student, Certification::factory()->published()->create(), withPdf: false);

        $this->actingAs($student)
            ->get(route('certificates.download', $certificate))
            ->assertNotFound();
    }
}
